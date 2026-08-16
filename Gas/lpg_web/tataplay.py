from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC

import re
import time

# Same Chrome setup as lpg_search.py/rc_print.py/hp_gas.py - see
# lpg_search.py's _create_driver() comment for why the anti-detection flags
# are needed (confirmed 2026-08-11: mysso.tataplay.com's WAF rejects a plain
# curl/requests GET outright with an F5-style "Request Rejected" page, but
# this same Chrome configuration gets through to the real login form).
from lpg_search import _create_driver, _quit_driver_with_timeout
from config import TATAPLAY_USERNAME, TATAPLAY_PASSWORD

SSO_URL = "https://mysso.tataplay.com/"

# Siebel's own quick-find result count line ("1 - 1 of 1", "1 - 13 of 13+",
# ...) - the ONLY reliable signal that a search actually matched a single
# real account, confirmed 2026-08-11: searching an unregistered mobile
# number doesn't return zero rows, it silently falls back to a list of
# unrelated recently-accessed accounts (also with a valid-looking "1 - N of
# N" line) instead of erroring. Treating anything other than exactly one
# result as "not found" is the only way to avoid handing back the wrong
# customer's data.
RESULT_COUNT_RE = re.compile(r"^\s*(\d+)\s*-\s*(\d+)\s*of\s*(\d+)\+?\s*$", re.MULTILINE)
FIELD_RE = re.compile(r"^(Account|Subscriber Id|Account Status)\s*:\s*(.*)$")


def _login(driver, wait):
    driver.get(SSO_URL)
    wait.until(lambda d: d.execute_script("return document.readyState") == "complete")

    driver.find_element(By.ID, "username").send_keys(TATAPLAY_USERNAME)
    driver.find_element(By.ID, "password").send_keys(TATAPLAY_PASSWORD)
    driver.find_element(By.CSS_SELECTOR, "input[type='submit']").click()
    wait.until(lambda d: d.execute_script("return document.readyState") == "complete")

    # The Siebel PRM app opens in a new tab/window via a plain
    # window.open(...) onclick handler, not a normal href - .click() still
    # fires that handler, so this needs a window-handle switch afterward
    # rather than following an href directly.
    original_handles = driver.window_handles
    prm_link = wait.until(EC.presence_of_element_located((By.XPATH, "//a[contains(.,'Siebel PRM')]")))
    prm_link.click()

    wait.until(lambda d: len(d.window_handles) > len(original_handles))
    new_handle = [h for h in driver.window_handles if h not in original_handles][0]
    driver.switch_to.window(new_handle)
    wait.until(lambda d: d.execute_script("return document.readyState") == "complete")


def _select_account_find(driver, wait):
    # The "Find" toolbar dropdown's "Account" option is indented with
    # non-breaking spaces (\xa0) in the real DOM, not plain ones - XPath's
    # normalize-space() only collapses ASCII whitespace and doesn't strip
    # those, so it never matches (confirmed 2026-08-11: a normalize-space()
    # XPath timed out here). Python's str.strip() does strip \xa0, so the
    # dropdown is located by checking each <select>'s options in Python
    # instead of trying to match the option text via XPath.
    wait.until(EC.presence_of_element_located((By.TAG_NAME, "select")))
    find_select, account_option = None, None
    for s in driver.find_elements(By.TAG_NAME, "select"):
        for o in s.find_elements(By.TAG_NAME, "option"):
            if o.text.strip() == "Account":
                find_select, account_option = s, o.text
                break
        if find_select:
            break
    if find_select is None:
        raise RuntimeError("Could not find the Find dropdown's Account option.")
    Select(find_select).select_by_visible_text(account_option)

    # field_textbox_1 = "Mobile Phone #" (field_textbox_0 is Subscriber Id,
    # 2-5 are Contact #1-4) - confirmed 2026-08-11 from the real form's
    # title attributes, which is more stable to key off than the
    # auto-generated ids alone.
    return wait.until(EC.visibility_of_element_located(
        (By.CSS_SELECTOR, "input[title='Mobile Phone #']")
    ))


def _parse_single_result(body_text):
    count_match = RESULT_COUNT_RE.search(body_text)
    if not count_match or count_match.group(1) != count_match.group(2) or count_match.group(2) != "1":
        return None

    fields = {}
    for line in body_text.splitlines():
        m = FIELD_RE.match(line.strip())
        if m:
            fields[m.group(1)] = m.group(2).strip()

    if not fields:
        return None
    return fields


# Field labels on the account DETAIL form (reached by drilling into the one
# quick-find result) that make up a postal address - confirmed 2026-08-16
# from a real account. Read via aria-label rather than title/name: these
# inputs don't expose a title attribute the way the quick-find's own search
# fields do.
ADDRESS_ARIA_LABELS = ["Address Line 1", "Address Line 2", "Village/Town/City", "District", "State", "Pin Code"]


def _open_account_detail_and_get_address(driver, wait):
    """
    Drills into the single quick-find result (a Siebel "drilldown" link that
    Selenium considers not-interactable via a normal .click() - it's styled
    TSLDisplayNone - so this fires the click via JS instead) and reads the
    account detail form's address fields. Returns a formatted address string,
    or "" if the detail page didn't load the expected fields in time.
    """
    link = wait.until(EC.presence_of_element_located((By.XPATH, "//a[@name='Title']")))
    driver.execute_script("arguments[0].click();", link)

    wait.until(lambda d: d.execute_script("return document.readyState") == "complete")

    deadline = time.time() + 15
    values = {}
    while time.time() < deadline:
        for label in ADDRESS_ARIA_LABELS:
            if label in values:
                continue
            els = driver.find_elements(By.CSS_SELECTOR, f"input[aria-label='{label}']")
            if els:
                values[label] = els[0].get_attribute("value") or ""
        if len(values) == len(ADDRESS_ARIA_LABELS):
            break
        time.sleep(0.5)

    parts = [values.get(label, "").strip() for label in ADDRESS_ARIA_LABELS]
    return ", ".join(p for p in parts if p)


def run_tataplay_single(mobile_number):
    """
    Logs into the Tata Play distributor SSO portal and runs a single Account
    quick-find by mobile number, returning
    {"mobileNumber", "found", "accountName", "subscriberId", "accountStatus", "address"}
    on an unambiguous single-account match, or {"mobileNumber", "found": False}
    otherwise (no match, or an ambiguous multi-result fallback list - see
    RESULT_COUNT_RE's comment). "address" is best-effort - drilling into the
    account detail form is a second page load that can fail independently of
    the search itself, so a found account with an unreadable address still
    comes back as found with address: "" rather than failing the whole search.
    """

    driver = None
    try:
        driver = _create_driver(headless=True)
        wait = WebDriverWait(driver, 20)

        _login(driver, wait)

        mobile_field = _select_account_find(driver, wait)
        mobile_field.send_keys(mobile_number)

        find_button = driver.find_element(By.XPATH, "//button[@title='Find']")
        find_button.click()
        wait.until(lambda d: d.execute_script("return document.readyState") == "complete")
        time.sleep(2)  # the results list re-renders client-side after the page "load" fires

        body_text = driver.find_element(By.TAG_NAME, "body").text
        fields = _parse_single_result(body_text)

        if not fields:
            return {"mobileNumber": mobile_number, "found": False}

        try:
            address = _open_account_detail_and_get_address(driver, wait)
        except Exception:
            address = ""

        return {
            "mobileNumber": mobile_number,
            "found": True,
            "accountName": fields.get("Account", ""),
            "subscriberId": fields.get("Subscriber Id", ""),
            "accountStatus": fields.get("Account Status", ""),
            "address": address,
        }

    finally:
        if driver is not None:
            _quit_driver_with_timeout(driver)

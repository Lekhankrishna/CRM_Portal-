from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import StaleElementReferenceException, TimeoutException

import time

# Same Chrome setup as lpg_search.py's own automation (headless + the
# anti-detection flags SDMS needed) - reused rather than duplicated, same
# reasoning as rc_print.py/hp_gas.py importing it.
from lpg_search import _create_driver, _quit_driver_with_timeout
from config import TATA_DTH_USERNAME, TATA_DTH_PASSWORD

# Distributor SSO entry point ("Jaya Durga Business Solutions 20287",
# confirmed working 2026-08-18) - a one-time Oracle Access Manager redirect
# link the user supplied directly, landing on a plain username/password
# login form. A bare curl/requests GET against this gets an instant "Request
# Rejected" from an edge WAF (confirmed - not a login failure, a whole-request
# block) even though the exact same URL renders fine in a real browser; only
# real Chrome (headless, with the same anti-automation-fingerprint flags
# lpg_search.py already uses for SDMS) gets through.
TATA_SSO_URL = (
    "https://mysso.tataplay.com/oam/server/obrareq.cgi?"
    "encquery%3DCtPtIKaUQGiF%2FfO%2FW0X6rC2XMbCgWg%2FfxuZOF3qHZlyMjypZoAnpIznxu1rpKoPmJ0lLmXJ8Xuy0SlMR3LzTCEdacGTveIAJ%2Fr4EE5WIm30A3TvGYHVBj4hEex6MT2%2FSm8QKXQg5AjPaT5QRgr7rqum2SJZmyCEbVdnHJF5lcPsL0W589rsvvv1dzsKgBXxdP0lKzNQMU%2B9wE75G%2FaYCY9ovbkKqbn9ofwvSmikOcu6nKaWsSbuwMoP1SJEWB26ohLSyqaGusIeXgS6cpJyLFQ%3D%3D%20agentid%3DWebgate_IDM%20ver%3D1%20crmethod%3D2"
    "&ECID-Context=1.0060oLddfKw89x1pzs4EyW0000IN004CVO%3BkXhgnXhglXjE0ZJOoOTLkKPOoLRKlSODoITT_G"
)
# The "Siebel PRM" partner portal, opened from the SSO welcome page's app
# icon (a window.open() there, no extra query string) - the OAM session
# cookie set on the mysso.tataplay.com domain family carries over here
# without a second login.
PRM_URL = "https://prm.tataplay.com/siebel/app/ssoprm/enu"

# The subscriber account fields worth showing an agent, in display order,
# keyed by this page's own aria-label text (confirmed against a real record,
#2026-08-18) - deliberately NOT "Mobile Phone #" (Siebel always renders it
# as a masked "**********" even to a logged-in distributor, so it carries no
# information the agent doesn't already have from the number they searched).
ACCOUNT_FIELDS = [
    ("Account Name", "Subscriber Name"),
    ("Subscriber Id", "Subscriber Id"),
    ("Status", "Status"),
    ("Account Type", "Account Type"),
    ("Account Category", "Account Category"),
    ("Account Sub-Category", "Account Sub-Category"),
    ("Tier", "Tier"),
    ("Sales Segment", "Sales Segment"),
    ("Balance Lock Status", "Balance Lock Status"),
]
ADDRESS_FIELDS = [
    ("Building Name", "Building Name"),
    ("Address Line 1", "Address Line 1"),
    ("Address Line 2", "Address Line 2"),
    ("Village/Town/City", "Village/Town/City"),
    ("Town", "Town"),
    ("District", "District"),
    ("Tahsil", "Tahsil"),
    ("State", "State"),
    ("Pin Code", "Pin Code"),
]
# Columns read from the "Products and Services" grid (one row per Digicard/
# set-top box on the account) - a plain data grid, not aria-labeled inputs
# like the fields above, so it needs its own header/row scrape. Must list
# EVERY column the grid actually renders, in order - zip() below aligns
# positionally, so omitting one (confirmed 2026-08-18: first cut of this
# list skipped "DigiComp Mfg. Serial Number" and silently shifted "Asset
# Type" to that column's value instead) misassigns every field after it
# rather than erroring. Labeled "Digicard Status" (not just "Status") since
# ACCOUNT_FIELDS already has its own "Status" (the account's, not the
# hardware's) - archiving flattens every section's fields into one row
# keyed by label (see includes/tata_dth_archive.php), where two fields
# sharing a label would silently collide.
DIGICARD_COLUMNS = [
    "Product", "Digicard #", "Digicomp #", "Digicard Type", "Digicard Status",
    "Effective Start Date", "DigiComp Mfg. Serial Number", "Asset Type",
]


def _field_value(driver, aria_label):
    els = driver.find_elements(By.CSS_SELECTOR, f'[aria-label="{aria_label}"]')
    if not els:
        return ""
    el = els[0]
    if el.tag_name == "select":
        selected = driver.execute_script(
            "return arguments[0].options[arguments[0].selectedIndex] ? "
            "arguments[0].options[arguments[0].selectedIndex].text : '';", el
        )
        return (selected or "").strip()
    return (el.get_attribute("value") or "").strip()


def _click_robust(driver, by, value, tries=5, settle=1.5):
    """Re-finds and clicks fresh each attempt. Siebel Open UI keeps
    re-rendering applets during its own bootstrap/navigation for a couple of
    seconds after an element first appears, so a reference grabbed even a
    moment earlier routinely goes stale before .click() runs (confirmed
    2026-08-18 - StaleElementReferenceException on both the "Accounts" nav
    link and the search-result drilldown link on different runs)."""
    last_err = None
    for _ in range(tries):
        try:
            el = WebDriverWait(driver, 10).until(EC.element_to_be_clickable((by, value)))
            el.click()
            return
        except (StaleElementReferenceException, TimeoutException) as e:
            last_err = e
            time.sleep(settle)
    raise last_err


def _login(driver, wait):
    driver.get(TATA_SSO_URL)
    username_field = wait.until(EC.presence_of_element_located((By.NAME, "username")))
    username_field.send_keys(TATA_DTH_USERNAME)
    driver.find_element(By.NAME, "password").send_keys(TATA_DTH_PASSWORD)
    driver.find_element(By.NAME, "password").submit()
    wait.until(lambda d: d.current_url != TATA_SSO_URL)


def _open_prm(driver, wait):
    driver.execute_script(f"window.open('{PRM_URL}', '_blank');")
    wait.until(lambda d: len(d.window_handles) > 1)
    driver.switch_to.window(driver.window_handles[-1])
    wait.until(EC.presence_of_element_located((By.LINK_TEXT, "Accounts")))
    # The nav bar itself is present at this point but Siebel is still mid-
    # bootstrap for a couple more seconds - see _click_robust's comment.
    time.sleep(3)


def _open_account_find_panel(driver, wait):
    """Clicks Accounts, then opens the "Find > Account" search panel,
    returning the now-visible Mobile Phone # input. Retries the dropdown
    selection - a plain Select().select_by_value() intermittently doesn't
    trigger Siebel's own jQuery-bound handler on this control (confirmed
    2026-08-18: the panel silently failed to open on roughly 1 in 3 runs
    even though the <select>'s value visibly "took")."""
    _click_robust(driver, By.LINK_TEXT, "Accounts")
    wait.until(EC.presence_of_element_located((By.ID, "findcombobox")))
    time.sleep(2)

    last_err = None
    for _ in range(4):
        try:
            Select(driver.find_element(By.ID, "findcombobox")).select_by_value("Account")
            driver.execute_script(
                "const el = document.getElementById('findcombobox');"
                "el.dispatchEvent(new Event('change', {bubbles: true}));"
                "if (window.jQuery) { jQuery(el).trigger('change'); }"
            )
            return WebDriverWait(driver, 6).until(
                EC.visibility_of_element_located((By.ID, "field_textbox_1"))
            )
        except (StaleElementReferenceException, TimeoutException) as e:
            last_err = e
            time.sleep(1)
    raise RuntimeError("Tata Sky DTH: the Account find panel never opened") from last_err


def _extract_digicards(driver):
    rows = driver.find_elements(By.CSS_SELECTOR, "table[id*='Product'] tbody tr, table.ui-jqgrid-btable tbody tr")
    cards = []
    for tr in rows:
        cells = [td.text.strip() for td in tr.find_elements(By.TAG_NAME, "td")]
        cells = [c for c in cells if c]
        if not cells:
            continue
        # The grid's own column order matches DIGICARD_COLUMNS for a normal
        # single-product account; zipped rather than indexed by a fixed
        # count so a shorter/reordered row degrades instead of crashing.
        card = {label: value for label, value in zip(DIGICARD_COLUMNS, cells)}
        if card:
            cards.append(card)
    return cards


def run_tata_dth_single(mobile_number):
    """
    Logs into the Tata Play distributor SSO, searches Siebel PRM's Accounts
    for the given mobile number, and returns
    {"mobileNumber", "found", "sections": [{"title", "fields": [{"label","value"}]}]}
    on a hit (same shape as hp_gas.py's run_hp_gas_single so the CRM's
    existing generic section renderer works unchanged), or
    {"mobileNumber", "found": False} on a miss.
    """
    driver = None
    try:
        driver = _create_driver(headless=True)
        wait = WebDriverWait(driver, 30)

        _login(driver, wait)
        _open_prm(driver, wait)
        mobile_field = _open_account_find_panel(driver, wait)

        mobile_field.clear()
        mobile_field.send_keys(mobile_number)
        mobile_field.send_keys(Keys.ENTER)

        # A genuine miss just never produces a drilldown link - Siebel
        # doesn't render an explicit "no records" message in this panel
        # (confirmed against the panel's own markup), so absence within a
        # generous window is the not-found signal, same as HP Gas's own
        # timeout-based miss handling.
        try:
            _click_robust(driver, By.CSS_SELECTOR, "span.TSLPRMSearchDrilldown a", tries=10, settle=2)
        except (StaleElementReferenceException, TimeoutException):
            return {"mobileNumber": mobile_number, "found": False}

        def account_name_populated(d):
            try:
                els = d.find_elements(By.CSS_SELECTOR, '[aria-label="Account Name"]')
                return bool(els and els[0].get_attribute("value"))
            except StaleElementReferenceException:
                return False
        wait.until(account_name_populated)
        time.sleep(2)  # let the rest of the applet's own re-render churn finish

        sections = []
        subscriber_fields = [
            {"label": display, "value": _field_value(driver, aria)}
            for aria, display in ACCOUNT_FIELDS
        ]
        subscriber_fields = [f for f in subscriber_fields if f["value"]]
        if subscriber_fields:
            sections.append({"title": "Subscriber Details", "fields": subscriber_fields})

        address_fields = [
            {"label": display, "value": _field_value(driver, aria)}
            for aria, display in ADDRESS_FIELDS
        ]
        address_fields = [f for f in address_fields if f["value"]]
        if address_fields:
            sections.append({"title": "Address", "fields": address_fields})

        for card in _extract_digicards(driver):
            sections.append({
                "title": f"Digicard ({card.get('Digicard #', '')})".strip(),
                "fields": [{"label": k, "value": v} for k, v in card.items()],
            })

        if not sections:
            return {"mobileNumber": mobile_number, "found": True, "rawText": driver.find_element(By.TAG_NAME, "body").text}

        return {"mobileNumber": mobile_number, "found": True, "sections": sections}

    finally:
        if driver is not None:
            _quit_driver_with_timeout(driver)

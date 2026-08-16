from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.keys import Keys

import re
import time

# Same locateme.services account/login as rc_print.py - reused rather than
# duplicated (see rc_print.py's own comment on why the Chrome setup itself
# is imported from lpg_search.py). Backs the CRM's "Locate Me" menu item.
from lpg_search import _create_driver, _quit_driver_with_timeout
from rc_print import _login

MOBILE_INFO_URL = "https://locateme.services/tools/mobile-info"

# A hit renders as one or more repeating "subscriber" cards (confirmed
# 2026-08-17 from a real result: a single mobile number returned 2 cards -
# the number has changed hands/been ported before, and the tool surfaces
# every linked record rather than just the latest one). Each card is a
# <div class="rounded-lg border ... shadow-2xl animate-in ..."> holding a
# name heading, a status badge, and a grid of field pairs - every field pair
# is a label <p class="text-[10px] ... tracking-widest ..."> immediately
# followed by a value <p>, so scraping by that shared structure works for
# whatever fields locateme.services shows (Primary Node, Alternate Node,
# Network Circle, Father's Name, Email Node, ID Linkage, Registry Address
# observed live), without hardcoding field names that might change.
CARD_XPATH = "//div[contains(@class,'rounded-lg') and contains(@class,'shadow-2xl') and contains(@class,'animate-in')]"
NAME_XPATH = ".//div[contains(@class,'text-2xl') and contains(@class,'font-black') and contains(@class,'uppercase')]"
BADGE_XPATH = ".//div[contains(@class,'inline-flex') and contains(@class,'rounded-full') and contains(@class,'border') and contains(@class,'text-xs')]"
FIELD_LABEL_XPATH = ".//p[contains(@class,'text-[10px]') and contains(@class,'tracking-widest')]"


def _extract_records(driver):
    cards = driver.find_elements(By.XPATH, CARD_XPATH)
    records = []
    for card in cards:
        name_els = card.find_elements(By.XPATH, NAME_XPATH)
        name = name_els[0].text.strip() if name_els else ""

        badge_els = card.find_elements(By.XPATH, BADGE_XPATH)
        status = badge_els[0].text.strip() if badge_els else ""

        fields = []
        for label_el in card.find_elements(By.XPATH, FIELD_LABEL_XPATH):
            label = label_el.text.strip()
            if not label:
                continue
            value_els = label_el.find_elements(By.XPATH, "following-sibling::p[1]")
            value = value_els[0].text.strip() if value_els else ""
            fields.append({"label": label, "value": value})

        if name or fields:
            records.append({"name": name, "status": status, "fields": fields})

    return records


def run_mobile_info_single(mobile_number):
    """
    Logs into locateme.services and runs the Mobile Info search for one
    mobile number, returning
    {"mobileNumber", "found", "records": [{"name","status","fields":[{"label","value"}]}]}
    on a hit, or {"mobileNumber", "found": False} on a miss.
    """

    driver = None
    try:
        driver = _create_driver(headless=True)
        wait = WebDriverWait(driver, 20)

        _login(driver, wait)

        driver.get(MOBILE_INFO_URL)

        number_input = wait.until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "input[placeholder='Enter Mobile Number']"))
        )
        number_input.clear()
        number_input.send_keys(mobile_number)
        number_input.send_keys(Keys.RETURN)

        deadline = time.time() + 30
        while time.time() < deadline:
            body_text = driver.find_element(By.TAG_NAME, "body").text

            for needle in ("not found", "no record", "invalid", "insufficient credit"):
                if needle in body_text.lower():
                    return {"mobileNumber": mobile_number, "found": False}

            # "Found N record(s)" only appears once result cards have
            # actually rendered (confirmed from a real hit) - waiting for it
            # avoids reading the card grid while it's still empty/mid-render.
            if re.search(r"found\s+\d+\s+record", body_text, re.IGNORECASE):
                records = _extract_records(driver)
                if records:
                    return {"mobileNumber": mobile_number, "found": True, "records": records}
                # Structure changed unexpectedly - fall back to raw text
                # rather than silently returning nothing.
                return {"mobileNumber": mobile_number, "found": True, "rawText": body_text}

            time.sleep(1)

        raise RuntimeError(f"Mobile Info timed out waiting for a result for {mobile_number}")

    finally:
        if driver is not None:
            _quit_driver_with_timeout(driver)

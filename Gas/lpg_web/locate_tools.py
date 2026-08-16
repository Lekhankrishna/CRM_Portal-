from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.keys import Keys

import time

from lpg_search import _create_driver, _quit_driver_with_timeout
from rc_print import _login

LOCATEME_BASE = "https://locateme.services/tools"

# Every tool on locateme.services shares the same page shell (confirmed
# 2026-08-17 - one text/email <input> with a placeholder, one submit
# <button>) and, for the two tools already automated by hand
# (mobile_info.py's Mobile Info, hp_gas.py's HP Gas Advanced), the same
# result-card component: a
# <div class="rounded-lg border ... shadow-2xl animate-in ..."> holding a
# name heading + status badge + a grid of label/value <p> pairs (Mobile
# Info), or repeating <h3> section headers each followed by
# "bg-muted/30 rounded-lg p-3" field cards (HP Gas Advanced). This module
# generalizes the Mobile Info card shape to drive every OTHER tool too,
# rather than hand-writing 20+ near-identical scripts - untested tools that
# render differently just fall through to the rawText capture at the bottom
# of run_tool_search() instead of crashing.
#
# whatsapp-dp is a known exception - it returns an image (a WhatsApp profile
# picture), not label/value fields, so this generic scraper will find no
# records for it. Left in the registry rather than excluded so a search
# still comes back with SOMETHING (rawText / the raw credit-spend), not a
# silent 404 in the CRM - proper image handling can be added later if it's
# actually needed.
TOOL_REGISTRY = {
    "mobile-info":              {"label": "Mobile Info",              "placeholder": "Enter Mobile Number",   "credits": 100},
    "vehicle-info":              {"label": "Vehicle Intelligence",     "placeholder": "Enter Vehicle Number",  "credits": 100},
    "aadhaar-info":               {"label": "Aadhaar Info",             "placeholder": "Enter Aadhaar Number",  "credits": 100},
    "sms-header-decode":         {"label": "SMS Header Decode",        "placeholder": "e.g. SGILTD",           "credits": 1},
    "imei-info":                 {"label": "IMEI Info",                "placeholder": "Enter 15-digit IMEI",   "credits": None},
    "aadhaar-to-ration":         {"label": "Aadhaar to Ration",        "placeholder": "Enter Aadhaar Number",  "credits": 50},
    "number-to-name":            {"label": "Number to Name",           "placeholder": "e.g. 9100721394",       "credits": 10},
    "number-to-facebook":        {"label": "Number to Facebook",       "placeholder": "e.g. 9717444994",       "credits": 5},
    "whatsapp-dp":               {"label": "WhatsApp DP Downloader",   "placeholder": "Mobile Number",         "credits": None},
    "aadhaar-to-pan":            {"label": "Aadhaar to PAN",           "placeholder": "e.g. 712481196833",     "credits": 50},
    "pan-to-gst":                {"label": "PAN to GST",               "placeholder": "e.g. ARCPV7418G",       "credits": 15},
    "vehicle-to-number":         {"label": "Vehicle to Number",        "placeholder": "e.g. UP70HQ2225",       "credits": 50},
    "indane-gas-info":           {"label": "Indane Gas Info",          "placeholder": "Enter 10-digit number", "credits": 100},
    "indane-gas-verification":   {"label": "Indane Gas v2",            "placeholder": "Enter mobile number",   "credits": 75},
    "bharat-gas-info":           {"label": "Bharat Gas Info",          "placeholder": "Enter Number",          "credits": None},
    "gmail-info":                {"label": "Gmail Info",               "placeholder": "example@gmail.com",     "credits": 35},
    "pan-info":                  {"label": "PAN Info",                 "placeholder": "Enter PAN Number",      "credits": 10},
    "vehicle-fastag":            {"label": "Vehicle Fastag",           "placeholder": "e.g. DL10C1234",        "credits": 50},
    "upi-finder":                {"label": "UPI Finder",               "placeholder": "e.g. 7982966659",       "credits": 25},
    "gst-info":                  {"label": "GST Info",                 "placeholder": "e.g. 09AAKCD6139J1Z8",  "credits": 25},
    "ifsc-info":                 {"label": "IFSC Info",                "placeholder": "e.g. SBIN0005383",      "credits": None},
    "ip-info":                   {"label": "IP Info",                  "placeholder": "e.g. 8.8.8.8",          "credits": None},
    "email-leak-check":          {"label": "Email Leak Check",         "placeholder": "user@example.com",      "credits": None},
    "sim-carrier-checker":       {"label": "SIM Carrier Checker",      "placeholder": "e.g. 9876543210",       "credits": None},
}

CARD_XPATH = "//div[contains(@class,'rounded-lg') and contains(@class,'shadow-2xl') and contains(@class,'animate-in')]"
NAME_XPATH = ".//div[contains(@class,'text-2xl') and contains(@class,'font-black') and contains(@class,'uppercase')]"
BADGE_XPATH = ".//div[contains(@class,'inline-flex') and contains(@class,'rounded-full') and contains(@class,'border') and contains(@class,'text-xs')]"
FIELD_LABEL_XPATH = ".//p[contains(@class,'text-[10px]') and contains(@class,'tracking-widest')]"

FAILURE_NEEDLES = (
    "not found", "no record", "no results", "no matching", "no data found",
    "invalid", "insufficient credit", "error occurred", "something went wrong",
)


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


def run_tool_search(tool_slug, query):
    """
    Logs into locateme.services and runs the given tool for one query value,
    returning {"toolSlug", "query", "found", "records": [...]} on a hit,
    {"toolSlug", "query", "found": False} on a clean miss, or
    {"toolSlug", "query", "found": True, "rawText": "..."} if the page
    rendered something but not in the expected card shape (untested tool,
    or a genuinely different result layout - see module docstring).
    """
    if tool_slug not in TOOL_REGISTRY:
        raise ValueError(f"Unknown locateme.services tool: {tool_slug}")

    driver = None
    try:
        driver = _create_driver(headless=True)
        wait = WebDriverWait(driver, 20)

        _login(driver, wait)

        driver.get(f"{LOCATEME_BASE}/{tool_slug}")

        # Every tool page has exactly one real (non-hidden) input (confirmed
        # 2026-08-17 across all 23 remaining tools) - grabbing the first one
        # generically avoids hardcoding each tool's exact placeholder text,
        # which is cosmetic and has already been seen to vary tool-to-tool.
        input_el = wait.until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "input:not([type='hidden'])"))
        )
        input_el.clear()
        input_el.send_keys(query)
        input_el.send_keys(Keys.RETURN)

        deadline = time.time() + 30
        while time.time() < deadline:
            body_text = driver.find_element(By.TAG_NAME, "body").text
            lower = body_text.lower()

            for needle in FAILURE_NEEDLES:
                if needle in lower:
                    return {"toolSlug": tool_slug, "query": query, "found": False}

            records = _extract_records(driver)
            if records:
                return {"toolSlug": tool_slug, "query": query, "found": True, "records": records}

            time.sleep(1)

        # Timed out without a clear card result or failure needle - capture
        # whatever's on screen rather than silently reporting nothing (e.g.
        # a tool whose result layout doesn't match CARD_XPATH at all, like
        # WhatsApp DP Downloader's image output - see module docstring).
        final_text = driver.find_element(By.TAG_NAME, "body").text
        return {"toolSlug": tool_slug, "query": query, "found": True, "rawText": final_text}

    finally:
        if driver is not None:
            _quit_driver_with_timeout(driver)

# Real SDMS login for lpg_search.py's Selenium automation - gitignored, see
# config.py.example for the template. This value is being rotated
# (2026-08-07) since the old one was found committed in git history.
USERNAME = "NOIDCC1"
PASSWORD = "Test@2026"

# Real LOCATEME login for rc_print.py's Selenium automation (also used by
# hp_gas.py, which imports rc_print.py's _login directly rather than
# duplicating it) - distinct names from SDMS's USERNAME/PASSWORD above
# since both live in this one config module. Found hardcoded directly in
# rc_print.py and moved out here, same reasoning as the SDMS credential.
LOCATEME_EMAIL = "Ashwanth@gmail.com"
LOCATEME_PASSWORD = "Ashwanth@gmail.com"

# Real Tata Play distributor SSO login (mysso.tataplay.com) for tataplay.py's
# Selenium automation - same reasoning as the SDMS/LOCATEME credentials above.
TATAPLAY_USERNAME = "Dis_20287"
TATAPLAY_PASSWORD = "Jaya@11566"

# Real app.cyfuture.co.in login ("LPG Emergency Helpline" - BPCL/HPCL/IOCL
# complaint portal) for indane_gas_pro.py's Selenium automation - same
# reasoning as the other credentials above.
CYFUTURE_USERNAME = "ahmedabad"
CYFUTURE_PASSWORD = "Charlie@2014"

from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Listen for all console events and print them
    page.on("console", lambda msg: print(f"Browser Console: {msg.text}"))

    # Navigate to the checkout page
    page.goto("http://127.0.0.1:8000")

    # Wait for the phone input to be visible and scroll to it
    phone_input = page.locator("input[name='phone']")
    phone_input.scroll_into_view_if_needed()
    expect(phone_input).to_be_visible()

    # Take a screenshot of the form
    page.screenshot(path="jules-scratch/verification/checkout_verification.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)
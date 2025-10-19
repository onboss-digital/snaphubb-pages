from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    page.goto("http://127.0.0.1:8000")

    # Wait for the page to be fully loaded
    page.wait_for_load_state('networkidle')

    # Change the language to Portuguese
    page.select_option('select#language-selector', 'br')

    # Wait for the page to update after language change
    page.wait_for_load_state('networkidle')

    # Take a screenshot of the main page with the security badge
    page.screenshot(path="jules-scratch/verification/main_page_with_badge.png")

    # Click on the PIX payment method
    pix_button = page.locator('div[wire\\:click="$set(\'selectedPaymentMethod\', \'pix\')"]')
    expect(pix_button).to_be_visible()
    pix_button.click()

    # Wait for the PIX form modal to be visible
    pix_form_modal = page.locator('#pix-form-modal')
    expect(pix_form_modal).to_be_visible()

    page.screenshot(path="jules-scratch/verification/pix_modal.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)

from playwright.sync_api import sync_playwright

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Go to the checkout page
    page.goto("http://127.0.0.1:8000")

    # Wait for the page to load completely
    page.wait_for_load_state("networkidle")

    # Change the language to Portuguese to make the PIX button visible
    page.select_option("#language-selector", "br")

    # Wait for Livewire to update the page after language change
    page.wait_for_load_state("networkidle")

    # Click the PIX payment method button
    pix_button = page.locator("button[wire\\:click=\"\\$set('selectedPaymentMethod', 'pix')\"]")
    pix_button.click()

    # Wait for the modal to appear using its ID
    modal = page.locator("#pix-modal-unified")
    modal.wait_for(state="visible")

    # Take a screenshot of the PIX modal
    page.screenshot(path="jules-scratch/verification/pix_modal.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)

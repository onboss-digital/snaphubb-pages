from playwright.sync_api import sync_playwright, expect

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

    # Verify the dynamic price is displayed correctly
    expect(modal.locator("p.text-green-400")).to_contain_text("R$ 39,90")

    # Fill out the form
    page.locator('input[wire\\:model\\.defer="cardName"]').fill("Test User")
    page.locator('input[wire\\:model\\.live\\.debounce\\.500ms="email"]').fill("test@example.com")
    page.locator('input[wire\\:model="phone"]').fill("11999999999")
    page.locator('input[wire\\:model\\.defer="cpf"]').fill("123.456.789-00")

    # Click the "Generate PIX" button
    page.locator('button[wire\\:click\\.prevent="startPixCheckout"]').click()

    # Wait for the QR code to appear
    page.wait_for_selector('div[x-show="step === \'qr_code\'"]')

    # Take a screenshot of the QR code modal
    page.screenshot(path="jules-scratch/verification/pix_qr_code.png")

    browser.close()

with sync_playwright() as playwright:
    run(playwright)

from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # 1. Navigate to the payment page
        page.goto("http://127.0.0.1:5173/")
        page.wait_for_load_state("networkidle")

        # 2. Select the PIX payment method
        pix_button = page.locator('div.payment-method-card[wire\\:click*="pix"]')
        pix_button.click()

        # 3. Fill in the user information
        page.locator('input[name="pix_name"]').fill("Jules Verne")
        page.locator('input[name="pix_email"]').fill("jules.verne@example.com")
        page.locator('input[name="pix_phone"]').fill("11999999999")
        page.locator('input[name="pix_cpf"]').fill("111.222.333-44")

        # 4. Click the checkout button
        page.locator("#checkout-button").click()

        # 5. Wait for the QR code to be displayed
        qr_code_locator = page.locator("img[src*='data:image/png;base64,']")
        expect(qr_code_locator).to_be_visible()

        # 6. Take a screenshot of the QR code
        page.screenshot(path="jules-scratch/verification/pix_qr_code.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
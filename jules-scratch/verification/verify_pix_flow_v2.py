from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    # Capture console logs
    page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))

    try:
        # 1. Navigate to the payment page
        page.goto("http://127.0.0.1:8000/")
        page.screenshot(path="jules-scratch/verification/01_initial_load.png")

        # 2. Wait for the page to be fully loaded
        page.wait_for_load_state("networkidle")
        page.screenshot(path="jules-scratch/verification/02_network_idle.png")

        # 3. Change language to Portuguese
        page.select_option('select[name="lang"]', 'br')
        page.wait_for_load_state("networkidle")
        page.screenshot(path="jules-scratch/verification/03_language_changed.png")

        # 4. Select the PIX payment method
        pix_button = page.locator('div.payment-method-card[wire\\:click*="pix"]')
        expect(pix_button).to_be_visible()
        pix_button.click()
        page.screenshot(path="jules-scratch/verification/04_pix_selected.png")

        # 4. Fill in the user information
        page.locator('input[name="pix_name"]').fill("Jules Verne")
        page.locator('input[name="pix_email"]').fill("jules.verne@example.com")
        page.locator('input[name="pix_phone"]').fill("11999999999")
        page.locator('input[name="pix_cpf"]').fill("111.222.333-44")
        page.screenshot(path="jules-scratch/verification/04_form_filled.png")

        # 5. Click the checkout button
        page.locator("#checkout-button").click()

        # 6. Wait for the processing modal to disappear
        expect(page.locator("#processing-modal")).to_be_hidden(timeout=15000)

        # 7. Wait for the QR code to be displayed
        qr_code_locator = page.locator("img[src*='data:image/png;base64,']")
        expect(qr_code_locator).to_be_visible(timeout=10000)

        # 8. Take a screenshot of the QR code
        page.screenshot(path="jules-scratch/verification/05_pix_qr_code.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)
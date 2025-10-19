import { chromium } from 'playwright';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto('http://localhost:8000');

  // Change language to Portuguese
  await page.selectOption('#language-selector', 'br');

  // Wait for any dynamic content to load after language change if necessary
  await page.waitForTimeout(1000); // Simple wait, can be replaced with more robust waits

  // Select PIX payment method to open the modal
  await page.click('button:has-text("PIX")');

  // Wait for the modal to appear
  await page.waitForSelector('#pix-modal-unified', { state: 'visible' });

  // Fill out the PIX form
  await page.fill('input[wire\\:model\\.defer="cardName"]', 'Test PIX Name');
  await page.fill('input[wire\\:model\\.live\\.debounce\\.500ms="email"]', 'test.pix@example.com');
  await page.fill('input[wire\\:model\\.defer="cpf"]', '123.456.789-00');

  // Click the "Generate PIX" button
  await page.click('button:has-text("Gerar PIX")');

  // Wait for the QR code to be displayed
  await page.waitForSelector('img[alt="PIX QR Code"]', { state: 'visible' });

  await page.screenshot({ path: 'screenshot.jpg' });
  await browser.close();
})();

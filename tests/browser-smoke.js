const { chromium } = require("playwright");

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
  await page.goto("http://127.0.0.1:8080/wp-login.php", { waitUntil: "networkidle" });
  await page.fill("#user_login", "admin");
  await page.fill("#user_pass", "password");
  await Promise.all([page.waitForNavigation({ waitUntil: "networkidle" }), page.click("#wp-submit")]);
  await page.goto("http://127.0.0.1:8080/wp-admin/admin.php?page=ciwc", { waitUntil: "networkidle" });
  if (!(await page.locator("h1").filter({ hasText: "Cost Importer for WooCommerce" }).count())) {
    throw new Error("Cost Importer admin page did not render");
  }
  await page.screenshot({ path: "screenshots/upload.png", fullPage: true });
  await browser.close();
})();

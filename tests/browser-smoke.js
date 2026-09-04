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

  await page.setInputFiles("#ciwc_csv", "samples/supplier-costs-eu.csv");
  await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle" }),
    page.getByRole("button", { name: "Upload and map columns" }).click(),
  ]);
  if (!(await page.getByRole("heading", { name: "Map supplier columns" }).count())) {
    throw new Error("Cost Importer mapping page did not render");
  }
  await page.screenshot({ path: "screenshots/mapping.png", fullPage: true });

  await page.locator('select[name="fixed_currency"]').selectOption("EUR");
  await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle" }),
    page.getByRole("button", { name: "Build safe preview" }).click(),
  ]);
  if (!(await page.getByRole("heading", { name: "Review before updating costs" }).count())) {
    throw new Error("Cost Importer preview did not render");
  }
  await page.screenshot({ path: "screenshots/preview.png", fullPage: true });

  await page.locator('input[name="confirmation"]').fill("UPDATE COSTS");
  await Promise.all([
    page.waitForNavigation({ waitUntil: "networkidle" }),
    page.getByRole("button", { name: "Apply reviewed cost updates" }).click(),
  ]);
  if (!(await page.getByText("Import recorded.").count())) {
    throw new Error("Cost Importer completion notice did not render");
  }
  await page.screenshot({ path: "screenshots/complete-history.png", fullPage: true });
  await browser.close();
})();

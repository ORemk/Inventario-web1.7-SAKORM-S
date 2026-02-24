Automated tests for login.html (Playwright)

Prerequisites
- Node.js (>=14) and `npm` installed on your machine.
- The application must be served by your local webserver at:
  `http://localhost/Sakorms.org/Inventory-web1.5/`

Install and run (one-time setup):

```bash
cd c:/xampp/htdocs/Sakorms.org/Inventory-web1.5
npm install --save-dev @playwright/test
npx playwright install
npx playwright test tools/tests/login.spec.js
```

Notes
- Tests run headless by default. To see the browser, add `--headed` to the `npx playwright test` command.
- If your server runs on a different host/port, update the `BASE` URL inside `login.spec.js` accordingly.

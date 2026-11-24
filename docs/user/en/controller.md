# Quick start: controller

From customer to paid invoice. Your role requires two-factor sign-in: see the employee quick start.

## Customer and contract

1. Open **Customers**, choose **New customer** and enter **Name** and **Billing address**.
2. Under **Tax** choose the **Tax status**. Enter the **VAT ID** or **Tax number**.
3. Under **Invoicing** set **Document language**, **Currency**, **Payment term (days)** and **Invoice format**.
4. Add a contact with email and turn on **Receives invoices**. Choose **Save**.
5. Open the project under **Projects** and choose **New contract**.
6. Enter **Number**, **Contract type**, **Budget (hours)** and **Round up to (minutes)**. Choose **Save**.

## Rates

1. Open **Hourly rates** and choose **New rate**.
2. Choose **Applies to**, then **Rate per hour**, **Currency** and **From**. Choose **Save**.
3. The most specific rate wins. Set rates before time is approved.
4. Use **End** to close a rate. Approved time that has a value keeps it.

Rate in another currency than the project? Under **Exchange rates** choose **New exchange rate**. A rate may be at most 31 days older than the work day. BAM and EUR are fixed.

## Month close and corrections

1. Open **Month close** and go to the month. Check how many entries are not approved.
2. Approved time wrong? Choose **Correct**, enter **Right time** and **Reason**, then **Save**. You cannot correct your own time.
3. Choose **Lock month**. It now takes no bookings, changes or corrections.
4. Need to change something? Choose **Reopen month** and give a **Reason**.

## Invoice from time

1. Open **Invoices** and choose **Invoice from time**.
2. Choose **Project**, **Service from** and **Service to**, then **Create draft**.
3. Check the lines. Use **Edit** to add your own.
4. Solve everything under **Before the invoice can be issued:**.
5. Choose **Issue invoice**. Confirm with your password or authenticator code; you come back to the draft.
6. Choose **Issue invoice** again and confirm. The invoice gets its number and never changes.

No project time? Choose **Free invoice**. A draft you do not need: **Discard draft**.

## Send, e-invoice, payment

1. Download the **PDF**. With **ZUGFeRD** the XML is inside it. **XRechnung** adds the button **XML**.
2. Choose **Send by email**, check the contacts and confirm. **Sent by email** shows the state. With XRechnung the XML is attached too.
3. Choose **Record payment** and enter **Amount** and **Date**. Part payments are possible.
4. **Open items** lists what is not paid yet, with days overdue.

XRechnung needs the **Leitweg-ID** and an email contact of the customer, and your contact data and **IBAN** in the settings. E-invoices are not yet checked with the official validators: check them before you rely on them.

## Cancel

1. Open the issued invoice and choose **Cancel invoice**. Confirm with your password.
2. A cancellation with its own number is issued. Its time can be billed again.
3. Payment recorded? Reverse it first with a negative payment. **Record payment** is shown only while an amount is open.

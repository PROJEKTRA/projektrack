# Quick start: administrator

Set up your company in one sitting. Installation and updates: `docs/admin/plain-hosting.md`. Your role requires two-factor sign-in: see the employee quick start. Some screens first ask for your password or an authenticator code.

## Users and roles

1. Open **Users** and choose **Invite**.
2. Enter **Name** and **Email**, choose one or more **Roles**, then **Send invitation**.
3. The person gets a mail with a link to set a password. The link is valid for 60 minutes. Later: **Forgot your password?** on the sign-in page.
4. Check the column **Two-factor**. It is required for **Administrator** and **Controller**.
5. Use **Change roles** later. You cannot change your own roles, and the last administrator keeps the role.

| Role | For |
|---|---|
| Administrator | Everything in the company |
| Controller | Customers, contracts, rates, month close, invoices |
| Project lead | Creating projects, customers (view) and their contacts |
| Employee | Booking own time |
| External | Booking own time |

Approval and service reports come with the project role, not with the role here.

## Settings

1. Open **Settings** in the main menu. Each group has its own **Save**.
2. Under **General** set **Organization language**, **Regional format** and **Time zone**.
3. Under **Time tracking** set **Description required from (minutes)** (0 turns it off) and **Warn from (minutes per day)**.

## Invoice settings

1. Under **Invoices** enter **Legal name on invoices**, **Street and number**, **Postal code** and **City**.
2. Enter **Tax number** or **VAT ID** and choose the **VAT status**.
3. Enter **Bank**, **IBAN** and **BIC**.
4. For e-invoices fill in **Contact person (e-invoices)**, **Phone (e-invoices)** and **Email (e-invoices)**.
5. Have an accountant check the legal notices. Only then turn on **Legal notices confirmed by an accountant**. Without it no invoice with a notice, for example reverse charge, can be issued.
6. Choose **Save**. Invoice numbers are given without gaps per year; there is nothing to set up.

## Languages and brand

1. Open **Languages**. Under actions choose **Activate** or **Deactivate**.
2. Choose **Edit texts** for your own wording. Keep the placeholders in braces.
3. Need another language? Choose **Copy a language**, then **Export**, translate and **Import**.
4. Open **Brand**. Set **Product name**, **E-mail sender name**, **Logo** and **Primary color**, then **Save**.

## License

1. Open **License**. Without a license the installation runs as **Community**, without a limit of users or time.
2. To install one, send the **Instance ID** to the licensor.
3. Choose the **License file** or paste the **License text**, then **Install license**. It is checked without an internet connection.

## System check

1. Open **System check** and choose **Check now**.
2. Reload the page after a minute. Solve **Critical** first, then **Warning**. Each problem says what to do.

**License** and **System check** are shown only to administrators of the company that operates the installation.

# Roles and Responsibilities

This is a working draft for legal validation under **Belgian / EU** data protection law (controller and processor roles as used in the GDPR and Belgian Act of 30 July 2018).

## Default Role Model

- Tenant organization: **Controller**
- WinProx platform operator: **Processor**

## Feature Matrix

| Feature | Data examples | Tenant role | WinProx role | Notes |
|---|---|---|---|---|
| Users and authentication | Name, email, role | Controller | Processor | Tenant decides who is invited |
| Buildings and units | Address, identifiers | Controller | Processor | Operational property data |
| Issues and tasks | Reports, descriptions, status | Controller | Processor | Maintenance workflow data |
| Owner records | Name, email, phone | Controller | Processor | Contact data for owner communication |
| Owner notification emails | Subject/body, recipients, send logs | Controller | Processor | Sent on tenant instruction |
| Activity logs | Actor, action, timestamps | Controller | Processor | Security and audit purpose |

## Decisions to Validate

- Is any processing done as independent controller by WinProx? `TODO`
- Are any subprocessors involved in email delivery or hosting? Cloud86 (EU hosting + daily backups), SMTP (EU), optional Ollama (local), optional Stripe (EU/US) — see public subprocessors page.
- Do contracts and privacy texts align with this matrix? `TODO`

<h2>1. Parties</h2>

<p>This data processing agreement is entered into between:</p>

<p><strong>Customer / administrator (tenant)</strong><br>
(the data controller)</p>

<p>and</p>

@include('partials.wp-legal-operator')

<p>(hereinafter “WinProx”, the processor)</p>

<p>
    This agreement is governed by Regulation (EU) 2016/679 (GDPR) and Belgian federal data protection law (Act of 30 July 2018); it implements Article 28 GDPR for processing on the customer’s instructions.
</p>

<h2>2. Subject matter</h2>

<p>
    WinProx processes personal data on the customer’s instructions in connection with use of the platform for
    site management, QR issue reporting and follow-up of issues and tasks.
</p>

<h2>3. Purpose of processing</h2>

<p>Processing includes:</p>

<ul>
    <li>management of issues and tasks</li>
    <li>management of users and internal teams</li>
    <li>management of workers (without login) and assignment to tasks</li>
    <li>management of locations and units</li>
    <li>sending email notifications on the customer’s instructions</li>
    <li>logging and security</li>
</ul>

<h2>4. Types of data</h2>

<ul>
    <li>identification data (name, email address, phone number where entered)</li>
    <li>location and unit data (addresses, location details)</li>
    <li>issue and task data (including photos and descriptions)</li>
    <li>data of workers and QR reporters, to the extent collected by the customer</li>
    <li>access and session data</li>
    <li>subscription and access metadata</li>
</ul>

<h2>5. WinProx obligations</h2>

<p>WinProx shall:</p>

<ul>
    <li>process data only on the customer’s instructions</li>
    <li>implement appropriate security measures</li>
    <li>restrict access to authorised persons</li>
    <li>ensure confidentiality</li>
</ul>

<h2>6. Security</h2>

<p>WinProx provides, among other things:</p>

<ul>
    <li>tenant isolation</li>
    <li>access control</li>
    <li>logging</li>
</ul>

<h2>7. {{ __('legal.documents.subprocessors') }}</h2>

<p>
    WinProx may use third parties for hosting, infrastructure, email and (where used) payments.
</p>

<p>
    These parties are carefully selected and subject to appropriate contractual safeguards. An up-to-date overview is available at
    <a href="{{ route('legal.subprocessors') }}">{{ __('legal.documents.subprocessors') }}</a>.
</p>

<h2>8. Data breaches</h2>

<p>
    WinProx will inform the customer without undue delay in the event of a personal data breach.
</p>

<h2>9. Data subject rights</h2>

<p>
    WinProx supports the customer in handling requests from data subjects.
</p>

<h2>10. Retention periods</h2>

<p>
    Data is retained according to the retention policy described in the
    <a href="{{ route('legal.privacy') }}">{{ __('legal.documents.privacy') }}</a>, including:
</p>
<ul>
    <li>user accounts: active + 24 months</li>
    <li>issues and tasks: contract period + 36 months</li>
    <li>logs: 6 months</li>
    <li>photos: 24 months after closing</li>
</ul>

<h2>11. End of agreement</h2>

<p>
    Upon termination of use of the platform:
</p>

<ul>
    <li>the customer may export data</li>
    <li>data will be deleted according to the retention policy</li>
</ul>

<h2>12. Liability</h2>

<p>
    WinProx’s liability is limited as set out in the
    <a href="{{ route('legal.terms') }}">{{ __('legal.documents.terms') }}</a>.
</p>

<h2>13. Governing law</h2>

<p>
    This agreement is governed by Belgian law.
</p>

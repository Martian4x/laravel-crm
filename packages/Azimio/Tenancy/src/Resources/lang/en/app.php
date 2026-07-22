<?php

return [
    'tenants' => [
        'title'           => 'Tenants',
        'create-title'    => 'Create Tenant',
        'company-name'    => 'Company Name',
        'admin-name'      => 'Admin Name',
        'admin-email'     => 'Admin Email',
        'admin-password'  => 'Admin Password',
        'locale'          => 'Language',
        'users'           => 'Users',
        'leads'           => 'Leads',
        'persons'         => 'Persons',
        'status'          => 'Status',
        'actions'         => 'Actions',
        'active'          => 'Active',
        'suspended-label' => 'Suspended',
        'create-btn'      => 'Create',
        'suspend-btn'     => 'Suspend',
        'activate-btn'    => 'Activate',
        'impersonate-btn' => 'Login as',
        'empty'           => 'No tenants yet.',
        'created'         => 'Tenant created successfully.',
        'suspended'       => 'Tenant suspended. Its users can no longer log in.',
        'activated'       => 'Tenant activated.',
        'no-user'         => 'This tenant has no active user to log in as.',
    ],

    'impersonation' => [
        'banner' => 'You are logged in as :tenant.',
        'revert' => 'Return to super admin',
    ],

    'register' => [
        'title'            => 'Register your Company',
        'company-name'     => 'Company Name',
        'your-name'        => 'Your Name',
        'email'            => 'Email Address',
        'password'         => 'Password',
        'confirm-password' => 'Confirm Password',
        'language'         => 'Language',
        'accept-terms'     => 'I agree to the :terms and the :privacy.',
        'submit-btn'       => 'Create Account',
        'sign-in-link'     => 'Already have an account? Sign In',
        'welcome'          => 'Welcome! :company is ready — start by adding your first lead.',
    ],

    'legal' => [
        'terms-title'      => 'Terms of Service',
        'privacy-title'    => 'Privacy Policy',
        'placeholder-note' => 'This is a template. Replace with text reviewed by your legal counsel before public launch.',

        'terms-sections' => [
            ['heading' => '1. The Service', 'body' => 'AzimioCustomers provides customer relationship management software as a service. Your company\'s account ("tenant") is operated under these terms by the account administrator who registered it.'],
            ['heading' => '2. Your Data', 'body' => 'Data you enter belongs to your company. We access it only to operate, support, and secure the service. You may request a full export of your data at any time.'],
            ['heading' => '3. Acceptable Use', 'body' => 'You agree not to use the service for unlawful purposes, to send spam, or to attempt to access other companies\' data. Accounts violating these rules may be suspended.'],
            ['heading' => '4. Availability & Support', 'body' => 'We aim for high availability but do not guarantee uninterrupted service. Support is provided during Tanzanian business hours.'],
            ['heading' => '5. Termination', 'body' => 'You may stop using the service at any time and request deletion of your company\'s data. We may suspend accounts for non-payment or breach of these terms after notice.'],
        ],

        'privacy-sections' => [
            ['heading' => '1. What We Collect', 'body' => 'Account details (name, email, company), the customer data your team enters, and basic usage logs needed to operate and secure the service.'],
            ['heading' => '2. How We Use It', 'body' => 'To provide the CRM service to your company, send service notifications, and improve reliability. We do not sell your data or use it for advertising.'],
            ['heading' => '3. Storage & Security', 'body' => 'Data is stored in a shared database isolated per company, with encrypted backups retained for 14 days. Access is limited to authorized platform staff.'],
            ['heading' => '4. Your Rights', 'body' => 'Your company\'s administrator may request an export or deletion of the company\'s data at any time by contacting support.'],
        ],
    ],
];

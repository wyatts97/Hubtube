<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            [
                'title' => 'Terms of Service',
                'slug' => 'terms-of-service',
                'content' => $this->termsOfService(),
            ],
            [
                'title' => 'Privacy Policy',
                'slug' => 'privacy-policy',
                'content' => $this->privacyPolicy(),
            ],
            [
                'title' => 'DMCA Takedown Policy',
                'slug' => 'dmca',
                'content' => $this->dmcaPolicy(),
            ],
            [
                'title' => 'Community Guidelines',
                'slug' => 'community-guidelines',
                'content' => $this->communityGuidelines(),
            ],
            [
                'title' => 'Cookie Policy',
                'slug' => 'cookie-policy',
                'content' => $this->cookiePolicy(),
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }

    protected function termsOfService(): string
    {
        return <<<'HTML'
<h2>1. Acceptance of Terms</h2>
<p>By accessing or using this website ("Service"), you agree to be bound by these Terms of Service ("Terms"). If you do not agree to all of these Terms, you may not use the Service.</p>

<h2>2. Eligibility</h2>
<p>You must be at least 18 years of age to use this Service. By using the Service, you represent and warrant that you are at least 18 years old and have the legal capacity to enter into these Terms.</p>

<h2>3. User Accounts</h2>
<p>To access certain features of the Service, you must create an account. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>

<h2>4. User Content</h2>
<p>You retain ownership of content you upload to the Service ("User Content"). By uploading User Content, you grant us a non-exclusive, worldwide, royalty-free license to use, reproduce, modify, distribute, and display your User Content in connection with operating the Service.</p>
<p>You represent and warrant that:</p>
<ul>
<li>You own or have the necessary rights to upload the User Content.</li>
<li>Your User Content does not infringe upon the intellectual property rights of any third party.</li>
<li>Your User Content complies with all applicable laws and these Terms.</li>
</ul>

<h2>5. Prohibited Content</h2>
<p>You may not upload, post, or transmit any content that:</p>
<ul>
<li>Depicts minors in any sexual or exploitative context.</li>
<li>Is non-consensual or depicts acts without the consent of all participants.</li>
<li>Promotes violence, harassment, or discrimination.</li>
<li>Infringes on the intellectual property rights of others.</li>
<li>Contains malware, viruses, or other harmful code.</li>
<li>Violates any applicable law or regulation.</li>
</ul>

<h2>6. Content Moderation</h2>
<p>We reserve the right to review, remove, or disable access to any User Content at our sole discretion, without prior notice, for any reason including violation of these Terms.</p>

<h2>7. Intellectual Property</h2>
<p>The Service and its original content (excluding User Content) are and will remain the exclusive property of the Service operator. The Service is protected by copyright, trademark, and other applicable laws.</p>

<h2>8. Termination</h2>
<p>We may terminate or suspend your account and access to the Service immediately, without prior notice, for conduct that we believe violates these Terms or is harmful to other users, us, or third parties, or for any other reason at our sole discretion.</p>

<h2>9. Disclaimer of Warranties</h2>
<p>The Service is provided "as is" and "as available" without warranties of any kind, either express or implied. We do not warrant that the Service will be uninterrupted, error-free, or free of viruses or other harmful components.</p>

<h2>10. Limitation of Liability</h2>
<p>To the fullest extent permitted by law, the Service operator shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or related to your use of the Service.</p>

<h2>11. Changes to Terms</h2>
<p>We reserve the right to modify these Terms at any time. We will notify users of material changes by posting the updated Terms on this page with a revised "Last Updated" date. Your continued use of the Service after changes constitutes acceptance of the new Terms.</p>

<h2>12. Governing Law</h2>
<p>These Terms shall be governed by and construed in accordance with the laws of the jurisdiction in which the Service operator is located, without regard to conflict of law principles.</p>

<h2>13. Contact</h2>
<p>If you have any questions about these Terms, please contact us through the contact information provided on the Service.</p>
HTML;
    }

    protected function privacyPolicy(): string
    {
        return <<<'HTML'
<h2>1. Information We Collect</h2>
<p>We collect information you provide directly to us, such as when you create an account, upload content, or contact us. This may include:</p>
<ul>
<li><strong>Account Information:</strong> Username, email address, password.</li>
<li><strong>Profile Information:</strong> Avatar, bio, and other optional profile details.</li>
<li><strong>Content:</strong> Videos, comments, and other content you upload or post.</li>
<li><strong>Payment Information:</strong> If you make purchases or receive payments through the Service.</li>
</ul>

<h2>2. Information Collected Automatically</h2>
<p>When you use the Service, we automatically collect certain information, including:</p>
<ul>
<li><strong>Log Data:</strong> IP address, browser type, operating system, referring URLs, and pages viewed.</li>
<li><strong>Device Information:</strong> Device type, screen resolution, and unique device identifiers.</li>
<li><strong>Usage Data:</strong> Videos watched, search queries, and interaction patterns.</li>
<li><strong>Cookies:</strong> See our Cookie Policy for more details.</li>
</ul>

<h2>3. How We Use Your Information</h2>
<p>We use the information we collect to:</p>
<ul>
<li>Provide, maintain, and improve the Service.</li>
<li>Process transactions and send related information.</li>
<li>Send notifications, updates, and promotional communications.</li>
<li>Monitor and analyze trends, usage, and activities.</li>
<li>Detect, investigate, and prevent fraudulent or unauthorized activity.</li>
<li>Comply with legal obligations.</li>
</ul>

<h2>4. Information Sharing</h2>
<p>We do not sell your personal information. We may share your information in the following circumstances:</p>
<ul>
<li><strong>With your consent:</strong> When you direct us to share information.</li>
<li><strong>Service providers:</strong> With third-party vendors who assist in operating the Service.</li>
<li><strong>Legal requirements:</strong> When required by law, regulation, or legal process.</li>
<li><strong>Safety:</strong> To protect the rights, property, or safety of our users or the public.</li>
</ul>

<h2>5. Data Retention</h2>
<p>We retain your information for as long as your account is active or as needed to provide the Service. You may request deletion of your account and associated data by contacting us.</p>

<h2>6. Data Security</h2>
<p>We implement reasonable security measures to protect your information. However, no method of transmission over the Internet or electronic storage is 100% secure, and we cannot guarantee absolute security.</p>

<h2>7. Your Rights</h2>
<p>Depending on your jurisdiction, you may have the right to:</p>
<ul>
<li>Access the personal information we hold about you.</li>
<li>Request correction of inaccurate information.</li>
<li>Request deletion of your information.</li>
<li>Object to or restrict certain processing of your information.</li>
<li>Data portability.</li>
</ul>

<h2>8. Children's Privacy</h2>
<p>The Service is not intended for individuals under 18 years of age. We do not knowingly collect personal information from children under 18. If we become aware that we have collected such information, we will take steps to delete it.</p>

<h2>9. Changes to This Policy</h2>
<p>We may update this Privacy Policy from time to time. We will notify you of material changes by posting the updated policy on this page with a revised "Last Updated" date.</p>

<h2>10. Contact</h2>
<p>If you have questions about this Privacy Policy, please contact us through the contact information provided on the Service.</p>
HTML;
    }

    protected function dmcaPolicy(): string
    {
        return <<<'HTML'
<h2>Overview</h2>
<p>We respect the intellectual property rights of others and expect our users to do the same. In accordance with the Digital Millennium Copyright Act of 1998 ("DMCA"), we will respond promptly to claims of copyright infringement committed using our Service.</p>

<h2>Filing a DMCA Takedown Notice</h2>
<p>If you believe that content on our Service infringes your copyright, please submit a written notification containing the following information:</p>
<ol>
<li><strong>Identification of the copyrighted work</strong> that you claim has been infringed.</li>
<li><strong>Identification of the infringing material</strong> and information sufficient to locate it on the Service (e.g., a URL).</li>
<li><strong>Your contact information:</strong> Name, address, telephone number, and email address.</li>
<li><strong>A statement</strong> that you have a good faith belief that use of the material is not authorized by the copyright owner, its agent, or the law.</li>
<li><strong>A statement</strong> that the information in the notification is accurate, and under penalty of perjury, that you are authorized to act on behalf of the copyright owner.</li>
<li><strong>Your physical or electronic signature.</strong></li>
</ol>

<h2>Counter-Notification</h2>
<p>If you believe your content was removed in error, you may submit a counter-notification containing:</p>
<ol>
<li><strong>Identification of the material</strong> that was removed and the location where it appeared before removal.</li>
<li><strong>A statement under penalty of perjury</strong> that you have a good faith belief the material was removed as a result of mistake or misidentification.</li>
<li><strong>Your name, address, and telephone number,</strong> and a statement that you consent to the jurisdiction of the federal court in your district.</li>
<li><strong>Your physical or electronic signature.</strong></li>
</ol>

<h2>Repeat Infringers</h2>
<p>We maintain a policy of terminating the accounts of users who are repeat infringers of copyright in appropriate circumstances.</p>

<h2>Contact for DMCA Notices</h2>
<p>DMCA notices should be sent to the designated copyright agent at the contact information provided on the Service. Please include "DMCA Takedown Notice" in the subject line.</p>
HTML;
    }

    protected function communityGuidelines(): string
    {
        return <<<'HTML'
<h2>Our Community Standards</h2>
<p>We are committed to maintaining a safe and respectful community. These guidelines apply to all content uploaded to and interactions on the Service.</p>

<h2>Content Rules</h2>
<ul>
<li><strong>Consent is mandatory.</strong> All individuals appearing in uploaded content must have given their explicit consent to be filmed and to have the content distributed.</li>
<li><strong>No minors.</strong> Content depicting or involving individuals under 18 years of age is strictly prohibited.</li>
<li><strong>No non-consensual content.</strong> Content depicting real or simulated non-consensual acts is prohibited.</li>
<li><strong>No harassment or hate speech.</strong> Content or comments that harass, bully, or promote hatred against individuals or groups are not allowed.</li>
<li><strong>No illegal content.</strong> Content that violates any applicable law is prohibited.</li>
<li><strong>No spam or misleading content.</strong> Do not upload misleading thumbnails, titles, or descriptions. Do not spam comments or messages.</li>
</ul>

<h2>Behavior Rules</h2>
<ul>
<li><strong>Be respectful.</strong> Treat other users with respect in comments and messages.</li>
<li><strong>No impersonation.</strong> Do not impersonate other users, public figures, or organizations.</li>
<li><strong>No doxxing.</strong> Do not share private information about other individuals without their consent.</li>
<li><strong>No scams.</strong> Do not attempt to defraud or scam other users.</li>
</ul>

<h2>Enforcement</h2>
<p>Violations of these guidelines may result in:</p>
<ul>
<li>Content removal.</li>
<li>Temporary or permanent account suspension.</li>
<li>Reporting to law enforcement where appropriate.</li>
</ul>

<h2>Reporting Violations</h2>
<p>If you encounter content or behavior that violates these guidelines, please use the Report feature on the Service or contact us directly.</p>
HTML;
    }

    protected function cookiePolicy(): string
    {
        return <<<'HTML'
<h2>What Are Cookies?</h2>
<p>Cookies are small text files stored on your device when you visit a website. They help the website remember your preferences and improve your experience.</p>

<h2>How We Use Cookies</h2>
<p>We use the following types of cookies:</p>
<ul>
<li><strong>Essential Cookies:</strong> Required for the Service to function properly (e.g., authentication, session management, CSRF protection).</li>
<li><strong>Preference Cookies:</strong> Remember your settings and preferences (e.g., theme, language).</li>
<li><strong>Analytics Cookies:</strong> Help us understand how users interact with the Service so we can improve it.</li>
</ul>

<h2>Third-Party Cookies</h2>
<p>Some third-party services integrated into our Service may set their own cookies. We do not control these cookies. Please refer to the respective third-party privacy policies for more information.</p>

<h2>Managing Cookies</h2>
<p>You can control and manage cookies through your browser settings. Please note that disabling certain cookies may affect the functionality of the Service.</p>
<p>Most browsers allow you to:</p>
<ul>
<li>View what cookies are stored and delete them individually.</li>
<li>Block third-party cookies.</li>
<li>Block cookies from specific sites.</li>
<li>Block all cookies.</li>
<li>Delete all cookies when you close your browser.</li>
</ul>

<h2>Changes to This Policy</h2>
<p>We may update this Cookie Policy from time to time. Changes will be posted on this page with a revised "Last Updated" date.</p>
HTML;
    }
}

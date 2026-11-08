<?php
/**
 * Email template: New application received
 *
 * Sent to the job's author (employer) whenever a candidate submits an
 * application to one of their listings.
 *
 * Available tokens:
 *   {site_name}, {site_url}, {admin_email}, {current_date}
 *   {employer_name}        - Display name of the job's author.
 *   {job_id}, {job_title}, {job_url}
 *   {application_id}       - The new application row ID.
 *   {applied_date}         - Date the candidate applied (formatted).
 *   {candidate_name}       - The applicant's submitted name.
 *   {candidate_email}      - The applicant's email address.
 *   {candidate_phone}      - The applicant's phone (may be empty).
 *   {cover_letter}         - The applicant's cover letter (may be empty).
 *   {application_status}   - Current status label (typically "Pending").
 *   {applications_url}     - Dashboard URL to the Applications section
 *                            filtered to this job.
 *
 * To customise, copy this file to:
 *   your-theme/workeera/emails/email-new-application.php
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>New application for {job_title}</title>
</head>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr>
            <td style="background:#2563eb;color:#ffffff;padding:20px 24px;font-size:18px;font-weight:700;">
                {site_name}
            </td>
        </tr>
        <tr>
            <td style="padding:28px 24px;">
                <h1 style="margin:0 0 12px;font-size:22px;color:#0f172a;">Hi {employer_name},</h1>
                <p style="margin:0 0 16px;font-size:15px;line-height:1.55;color:#334155;">
                    You have a new application for <strong>"{job_title}"</strong>.
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;">
                    <tr>
                        <td style="padding:14px 16px;">
                            <p style="margin:0 0 6px;font-size:13px;color:#64748b;">Applicant</p>
                            <p style="margin:0 0 10px;font-size:16px;font-weight:600;color:#0f172a;">{candidate_name}</p>
                            <p style="margin:0 0 4px;font-size:13px;color:#475569;">
                                <a href="mailto:{candidate_email}" style="color:#2563eb;text-decoration:none;">{candidate_email}</a>
                            </p>
                            <p style="margin:0;font-size:13px;color:#475569;">{candidate_phone}</p>
                        </td>
                    </tr>
                </table>

                <p style="margin:16px 0 8px;font-size:13px;color:#64748b;">Cover letter</p>
                <p style="margin:0 0 24px;font-size:14px;line-height:1.55;color:#334155;white-space:pre-wrap;">{cover_letter}</p>

                <p style="margin:0 0 8px;">
                    <a href="{applications_url}" style="display:inline-block;background:#2563eb;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">Review the application</a>
                </p>

                <p style="margin:24px 0 0;font-size:12px;color:#64748b;">
                    Application #{application_id} &middot; Applied {applied_date}
                </p>
            </td>
        </tr>
        <tr>
            <td style="background:#f8fafc;padding:16px 24px;border-top:1px solid #e5e7eb;font-size:12px;color:#64748b;">
                You're receiving this notification because you posted a job on <a href="{site_url}" style="color:#2563eb;text-decoration:none;">{site_name}</a>.
            </td>
        </tr>
    </table>
</body>
</html>

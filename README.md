# Workeera | AI-Powered WordPress Job Board & Applicant Tracking System

[![WordPress Plugin Version](https://img.shields.io/wordpress/plugin/v/workeera-remote-tech-job-board)](https://wordpress.org/plugins/workeera-remote-tech-job-board/)
[![WordPress Plugin Downloads](https://img.shields.io/wordpress/plugin/dt/workeera-remote-tech-job-board)](https://wordpress.org/plugins/workeera-remote-tech-job-board/)
[![WordPress Plugin Rating](https://img.shields.io/wordpress/plugin/rating/workeera-remote-tech-job-board)](https://wordpress.org/plugins/workeera-remote-tech-job-board/)
[![License](https://img.shields.io/wordpress/plugin/l/workeera-remote-tech-job-board)](https://wordpress.org/plugins/workeera-remote-tech-job-board/)

**Workeera** is a complete AI-powered job board solution for WordPress, with an Applicant Tracking System (ATS) that helps automate screening and speed up applications for recruiters and job seekers.

Workeera automates time-consuming parts of hiring. **AI applicant match scoring** ranks applications against a job and provides reasoning behind each score, helping recruiters review suitable candidates first. **AI resume parsing** and a **candidate resume builder** help job seekers prepare and submit applications more quickly.

Workeera turns a WordPress website into a fully featured job board. Employers can register, publish jobs, and review applications from a dedicated front-end dashboard. Candidates can build their profiles, upload CVs, browse jobs, and apply.

- **WordPress.org:** [Workeera – Remote Tech Job Board](https://wordpress.org/plugins/workeera-remote-tech-job-board/)
- **Website:** [workeera.com](https://workeera.com/)
- **AI services:** [Hiremium](https://hiremium.com/)

Jobs, candidates, taxonomies, applications, and related records are managed in dedicated custom database tables rather than relying on `wp_posts` and `wp_postmeta` for the core job-board data.

The AI add-ons connect to the Workeera AI service. To enable them, create an account at [Hiremium](https://hiremium.com/), generate an API key from the dashboard, and enter it under **Workeera → Settings → General** on your WordPress site.

## Table of contents

- [Screenshots](#screenshots)
- [Key features](#key-features)
- [Built for developers](#built-for-developers)
- [Installation](#installation)
- [Requirements](#requirements)
- [Frequently asked questions](#frequently-asked-questions)
- [Uninstalling](#uninstalling)

## Screenshots

### 1. Front-end job listings

Job listings with keyword search, sorting, and filters for sector, skills, job type, experience, salary range, and location.

![Workeera front-end job listings](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-1.png)

### 2. Employer dashboard

A dashboard overview with job-posting statistics, active and pending jobs, total applicants, and recent applications.

![Workeera employer dashboard](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-2.png)

### 3. Employer – Manage jobs

Employers can manage job listings, check application counts, feature jobs, and review listing statuses.

![Workeera manage jobs](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-3.png)

### 4. Employer – Job applications

Review applicants and manage application progress with status changes and bulk actions.

![Workeera employer job applications](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-4.png)

### 5. Candidate dashboard

Candidates can view their application activity, reviewed applications, shortlisted jobs, and recent applications.

![Workeera candidate dashboard](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-5.png)

### 6. Candidate profile

Candidates can manage personal details, contact information, professional links, and other profile information.

![Workeera candidate profile](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-6.png)

### 7. Recommended jobs

A job discovery view with recommended listings, job-type information, sorting, and quick application links.

![Workeera recommended jobs](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-7.png)

### 8. CV / Resume manager

Candidates can upload and manage multiple CV or resume files.

![Workeera CV manager](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-8.png)

### 9. Applied jobs

Candidates can review jobs they have applied for and check each application's current status.

![Workeera applied jobs](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-9.png)

### 10. WordPress admin – Jobs

Administrators can manage job listings, including their status, employer, application count, dates, and bulk actions.

![Workeera admin jobs](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-10.png)

### 11. WordPress admin – Applications

Administrators can search and filter applications and review applicant details and application statuses.

![Workeera admin applications](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-11.png)

### 12. AI applicant match score

The AI integration provides a candidate match score along with an explanation of how the candidate's experience and skills align with the job.

![Workeera AI applicant match score](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-12.png)

### 13. Plugin settings

Configure job submission and application behavior, including listing duration, location options, submission limits, and application methods.

![Workeera plugin settings](https://workeera.com/git/workeera-job-board-plugin/screenshots/screenshot-13.png)

## Key features

### AI and automation

- AI applicant match scoring, with per-candidate reasoning.
- AI resume parsing.
- Candidate resume builder.
- Automated job alerts.
- AI capabilities are provided through Workeera's official AI service and add-ons.

### Job listings and discovery

- Front-end job listings with keyword search and sorting.
- Filters for sectors, skills, job types, experience, salary range, and location.
- Country and city location support.
- Featured and urgent job labels.
- Configurable job listing duration, deadlines, and expiry.
- Google Job Posting structured data (schema.org JSON-LD) for job listings.

### Employer dashboard

- Dedicated front-end employer dashboard with live statistics.
- Create and manage job listings.
- Edit, expire, and feature jobs.
- View applications for each job.
- Review applicants and update application statuses.
- Bulk application status changes, including reviewed, shortlisted, and rejected.

### Candidate dashboard

- Dedicated front-end candidate dashboard.
- Candidate profile management.
- CV / resume manager supporting multiple files.
- Browse and apply for jobs.
- View applied jobs and application statuses.
- Recommended jobs.

### Applications and hiring workflow

- Applications stored in a dedicated database table.
- Internal application form, external application URL, or both modes.
- Configurable application form fields, including phone, CV / resume, and cover letter.
- Application status tracking.
- A CV snapshot is saved for each application.
- CV downloads handled through a permission-checked process.
- Email notifications for first-time job publication, new applications, and application-status changes.

### Filtering and custom data

- Custom taxonomies with dedicated database tables and admin screens:
  - Sectors
  - Skills
  - Job Types
  - Time Zones
  - Benefits
  - Experiences
- Selecting a sector dynamically narrows the available skills in job listings, the job-posting form, candidate profiles, and the admin area.

### WordPress administration

- Admin screens for Jobs, Candidates, and Applications.
- Custom columns and filters, including employer and status filters.
- Workeera dashboard with site-wide statistics.
- Bulk actions for managing records.

### Developer and customization features

- Theme-overridable front-end templates.
- Theme-overridable email templates.
- Translation-ready strings using the `workeera-remote-tech-job-board` text domain.
- Scheduled WP-Cron tasks for job expiry, orphan application cleanup, term-count refresh, and stale transient cleanup.
- Custom database tables for jobs, candidates, applications, taxonomy terms, term relationships, term counts, and plugin transients.

## Built for developers

- Object-oriented, PSR-style class structure.
- WordPress-standard PHPDoc / JSDoc documentation on public classes and functions.
- Filterable AJAX endpoints using a central `workeera_action` dispatcher alongside standard `wp_ajax_*` hooks.
- Custom-table CRUD helper: `Workeera_Query_Handler`.
- Meta helper functions: `workeera_get_object_meta()` and `workeera_update_object_meta()`.
- Theme-overridable view layer using `workeera_get_template_part( $slug, $name, $ext )`.

## Installation

### Install from WordPress.org

1. In your WordPress admin area, go to **Plugins → Add New Plugin**.
2. Search for **Workeera – Remote Tech Job Board**.
3. Click **Install Now**, then **Activate**.

### Manual installation

1. Download the plugin from the [WordPress.org plugin page](https://wordpress.org/plugins/workeera-remote-tech-job-board/).
2. Upload the `workeera-remote-tech-job-board` folder to the `/wp-content/plugins/` directory, or upload the ZIP through **Plugins → Add New Plugin → Upload Plugin**.
3. Activate Workeera from the **Plugins** screen.

### Initial setup

1. On first activation, Workeera launches a guided setup wizard.
2. The wizard creates the Dashboard and Jobs listing pages with the `[workeera_user_dashboard]` and `[workeera_jobs_listing]` shortcodes already added.
3. It assigns the pages in settings and guides you through key options such as salary unit, location filter type, maximum job submissions per 24 hours, and application method.
4. Review and adjust the remaining options under **Workeera → Settings**.
5. Optionally, open the taxonomy screens and use **Import Sample Data** to add starter sectors, skills, job types, and other terms.

You can run the wizard again at any time from **Workeera → Setup Wizard**.

### Manual page setup

If you prefer not to use the wizard:

1. Create a page for the user dashboard and add `[workeera_user_dashboard]`.
2. Create a page for job listings and add `[workeera_jobs_listing]`.
3. Assign both pages under **Workeera → Settings → Pages**.
4. Configure the remaining options under **Workeera → Settings**.

## Requirements

- WordPress 6.0 or newer
- PHP 8.0 or newer
- MySQL 8.0 or newer, or MariaDB 10.5 or newer

## Frequently asked questions

### Can the same user be both an employer and a candidate?

No. Each user is assigned a single `workeera_user_type` (candidate or employer) the first time they access the dashboard. Workeera automatically creates the matching candidate record for candidate users.

### Where are CVs stored?

Candidate CVs are stored in:

`wp-content/uploads/wp-workeera/cv-files/`

When a candidate applies for a job, a snapshot copy is saved under:

`wp-content/uploads/wp-workeera/application-files/<year>/<month>/`

The application uses that snapshot, so it remains associated with the CV submitted at the time of applying. Direct file URLs are not exposed; downloads go through a permission-checked handler.

### Can a job link to an external application URL instead of using the on-site application form?

Yes. Set **Job apply method** under **Workeera → Settings** to **External**, **Internal**, or **Both**. When External is enabled (or Both is selected and the job is configured for external applications), the Apply button links to the configured external URL.

### Can I customize email notifications?

Yes. The three notification emails (`job-published`, `new-application`, and `application-status`) are available as templates under `templates/emails/`.

Copy the relevant template into your theme using this path:

`your-theme/workeera-remote-tech-job-board/emails/email-<slug>.php`

You can then customize the template. The `{token}` placeholders documented at the top of each template are replaced before the email is sent.

### Does Workeera support translations?

Yes. User-facing strings use the `workeera-remote-tech-job-board` text domain. Translation files can be placed in `/wp-content/languages/plugins/` or in the plugin's own `languages/` folder.

### How does Workeera perform on larger sites?

Jobs, applications, and taxonomy relationships are stored in dedicated indexed tables rather than relying on `wp_postmeta` for core job-board queries. Scheduled maintenance tasks are batched and checkpointed using the plugin's custom transients table.

### How do I uninstall Workeera?

Deactivate and delete the plugin from the **Plugins** screen.

Deactivation intentionally preserves custom database tables and uploaded application files so that data is not lost if you reactivate the plugin. For a complete manual removal, delete the `workeera_*` tables and the `wp-content/uploads/wp-workeera/` directory after taking a backup.

## Author & Contact

**Ali Malik**

-   **Live demo:** [demo.workeera.com](https://demo.workeera.com/)
-   **Portfolio / company:** [workeera.com](https://workeera.com/)
-   **LinkedIn:**
    [linkedin.com/in/ali-malik-a8677067](https://www.linkedin.com/in/ali-malik-a8677067)
-   **Email:** <hello@workeera.com> · <malik.mohamad.ali@gmail.com>

------------------------------------------------------------------------

## License

This repository includes an MIT `LICENSE` file. The project description
supplied for this README also states that the project is intended for
portfolio demonstration purposes and that all rights are reserved.
**Please confirm the intended licensing terms before publishing:** the
MIT license and an "all rights reserved" notice communicate different
permissions. This README does not change the repository's existing
`LICENSE` file.
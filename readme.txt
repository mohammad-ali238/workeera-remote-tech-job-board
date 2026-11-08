=== Workeera – AI Job Board with Applicant Tracking System (ATS) ===
Contributors: alimalik238
Tags: job board, jobs, careers, recruitment, hiring
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete AI powered job board solution for WordPress — with Applicant Tracking System (ATS), automating screening and speeding up applications to save time for recruiters and job seekers.

== Description ==

Take hiring further with **Workeera** that automate the slow parts: **AI applicant match scoring** ranks every application against the job — with the reasoning behind each score — so recruiters review the best-fit candidates first, while **AI resume parsing** and a **candidate resume builder** let job seekers apply in minutes. The result is less busywork, faster hiring, and a smoother experience for candidates.

**Workeera** turns any WordPress site into a fully featured job board. Employers can register, post jobs and review applications from a dedicated front-end dashboard. Candidates can build their profile, upload CVs, browse jobs, and apply with a single click. Learn more at [workeera.com](https://workeera.com).

Everything — jobs, candidates, taxonomies, applications and CV files — is managed in dedicated custom database tables for performance, so your `wp_posts` and `wp_postmeta` stay clean.

The AI add-ons connect to the Workeera AI service. To enable them, create an account at [Hiremium](https://hiremium.com), generate your API key from the dashboard, then paste it into **Workeera → Settings → General** on your site.

= Key features =

* **AI &amp; automation ready** — extend with free &amp; official add-ons for AI applicant match scoring (with per-candidate reasoning), AI resume parsing, a candidate resume builder, and automated job alerts — automating screening and speeding up applications to save time for recruiters and job seekers.
* **Front-end dashboards** for candidates and employers, with a section-aware top bar (breadcrumbs + menu) and a dashboard home with live stats.
* **Job listings** page with keyword search, sorting, sticky/featured jobs, and rich filters: sector, skills, job type, experience, salary range, location (country/city), and more.
* **Smart filtering** — selecting a sector dynamically narrows the available skills, across listings, the job-posting form, the candidate profile and the admin.
* **Custom taxonomies** with their own tables and admin screens: Sectors, Skills, Job Types, Time Zones, Benefits, Experiences.
* **Apply flow** — applications are stored in a dedicated table and the candidate's CV is copied into a private `application-files` folder so each application keeps its own snapshot.
* **External apply mode** — jobs can route applicants to an external URL instead of the in-site application popup, configurable per job.
* **Employer dashboard** — post jobs, manage jobs (edit/expire/feature), and review applications with bulk status changes (reviewed / shortlisted / rejected).
* **Candidate dashboard** — applied jobs, recommended jobs, CV manager (multiple CVs per candidate), and editable profile.
* **wp-admin Jobs/Candidates/Applications screens** with custom columns, employer filter, status filter, and a Workeera Dashboard page with site-wide stats.
* **Email notifications** for first-time job publication, new applications received, and application-status changes — fully themeable via template overrides.
* **Scheduled tasks** (WP-Cron) to expire jobs past their deadline, clean orphan application rows, refresh term counts, and purge stale transients.
* **Theme-overridable templates** — every front-end template can be overridden from the active theme's `workeera-remote-tech-job-board/` folder.
* **Translation ready** — all user-facing strings use the `workeera-remote-tech-job-board` text domain with proper `translators:` comments on placeholder strings.
* **Custom DB tables** for jobs, candidates, applications, taxonomy terms, term relations, term counts and a plugin transients store, with batched cron jobs to keep everything tidy.
* **Google Job Posting Structured Data for Job Search** — jobs are automatically marked up with the required schema.org JSON-LD for Google Job Search.

= Built for developers =

* Object-oriented, PSR-style class structure.
* WordPress-standard PHPDoc / JSDoc on every public class and function.
* Filterable AJAX endpoints scoped through a single `workeera_action` dispatcher plus standard `wp_ajax_*` hooks.
* Parallel custom-table CRUD helper (`Workeera_Query_Handler`) and meta helper (`workeera_get_object_meta` / `workeera_update_object_meta`).
* Theme-overridable view layer via `workeera_get_template_part( $slug, $name, $ext )`.

== Installation ==

1. Upload the `workeera-remote-tech-job-board` folder to the `/wp-content/plugins/` directory, or install the plugin through the WordPress **Plugins** screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. **Run the Setup Wizard — Workeera does the work for you.** On first activation the plugin launches a guided setup wizard that sets everything up automatically: it creates the Dashboard and Jobs listing pages for you (with the correct `[workeera_user_dashboard]` and `[workeera_jobs_listing]` shortcodes already in place), assigns them in settings, and walks you through the key general options (salary unit, location filter type, max jobs per 24 hours, apply-method, etc.). In a couple of clicks your job board is live — no manual page-building required. You can re-run the wizard any time from **Workeera &rarr; Setup Wizard**.
4. **Prefer to set things up by hand?** You can skip the wizard and do it manually instead: create the pages yourself, add the `[workeera_user_dashboard]` and `[workeera_jobs_listing]` shortcodes (or assign existing pages in **Workeera &rarr; Settings &rarr; Pages**), then fine-tune the rest under **Workeera &rarr; Settings**.
5. (Optional) Populate the taxonomy screens (Sectors, Skills, Job Types, etc.) with starter terms — or just click the **Import Sample Data** button on each taxonomy screen and Workeera fills them in for you.

= Requirements =

* WordPress 6.0 or newer
* PHP 8.0 or newer
* MySQL 8.0 or newer (or MariaDB 10.5+)

== Frequently Asked Questions ==

= Can the same user be both an employer and a candidate? =

No. Each user is assigned a single `workeera_user_type` (candidate or employer) the first time they reach the dashboard. The plugin scaffolds the matching candidate post automatically for candidate users.

= Where are CVs stored? =

In `wp-content/uploads/wp-workeera/cv-files/` for the candidate's CV manager. When a candidate applies to a job, a snapshot copy is written to `wp-content/uploads/wp-workeera/application-files/<year>/<month>/` and that copy is the one the employer sees. Direct file URLs are not exposed — downloads go through a permission-checked handler.

= Can a job link to an external application URL instead of using the on-site popup? =

Yes. Set the **Job apply method** option in **Workeera &rarr; Settings** to *External*, *Internal*, or *Both*. When *External* (or *Both* with the per-job choice set to External) is selected, the Apply button on the job page becomes an outbound link to the configured URL.

= Can I customize the email notifications? =

Yes. The three notification emails (`job-published`, `new-application`, `application-status`) live as templates under `templates/emails/`. Copy the file into your theme at `your-theme/workeera-remote-tech-job-board/emails/email-<slug>.php` and edit it freely. The `{token}` placeholders documented at the top of each template are replaced before sending.

= Does the plugin support translations? =

Yes — every user-facing string uses the `workeera-remote-tech-job-board` text domain, and strings with placeholders (`%s`, `%d`) carry `translators:` comments so that WP-CLI's i18n tools generate accurate `.pot` entries. Drop your translation files into `/wp-content/languages/plugins/` or under the plugin's own `languages/` folder.

= How does the plugin perform on large sites? =

Jobs, applications and taxonomy relations live in dedicated indexed tables (not `wp_postmeta`), so filtering listings and counting applications stays fast as data grows. Cron jobs that recount terms and clean orphan rows are batched (capped per run) and checkpointed via a custom transients table.

= How do I uninstall? =

Deactivate and delete the plugin from the **Plugins** screen. The custom tables and uploaded application files are intentionally preserved on deactivation so you can re-enable without data loss. If you need a clean removal, drop the `workeera_*` tables and the `wp-content/uploads/wp-workeera/` folder manually.

== Screenshots ==

1. Front-end job listings with the filter sidebar (sector, skills, location, salary).
2. Employer dashboard - main window with stats.
3. Employer dashboard — Manage Jobs view with applications count per job.
4. Employer dashboard — Job Applications screen with bulk actions and status filter.
5. Candidate dashboard - main window with stats.
6. Candidate dashboard — personal information.
7. Candidate dashboard — recommended jobs.
8. Candidate dashboard — CV/Resume files manager.
9. Candidate dashboard — Recent applications with status.
10. wp-admin Workeera jobs listing with status and bulk actions.
11. wp-admin Job Applications list table with employer + status filters.
12. Job Applicants match score with clear reason.
13. Admin settings.

== Changelog ==

= 1.0.7 =
* Translation issues fixed.

= 1.0.6 =
* Security Fix: Limited saved metadata to allowed fields only, preventing unauthorized data from being stored.
* Thanks to Artus KG and Shhriyash for the issues they find out and reported.

= 1.0.5 =
* Free AI credits added just with email verification.
* Add multiple companies record per employer account.

= 1.0.4 =
* Employer user profile settings added in dashboard.
* Employer account enable/disable option added in General Settings.

= 1.0.3 =
* Added a dynamic configuration option to set a maximum character limit for job descriptions.
* Job applications now display a popup with the cover letter content for quick preview.
* Resolved various minor bugs to improve overall stability and performance.

= 1.0.2 =
* When a candidate withdraws their job application, the record is now kept in the database instead of being deleted
* Application status is changed to "Withdrawn" for better record keeping and reporting
* Added settings to toggle job application form fields on/off
* You can now enable or disable these fields individually: Phone, CV/Resume, Cover Letter

= 1.0.1 =
* Fixed a bug where the plugin version was incorrectly set to '1.0.' instead of '1.0.0' in the `Workeera_Plugin` class.
* Added google job posting structured data (JSON-LD) to job listings for better visibility in Google Job Search.

= 1.0.0 =
* Initial release.
* Custom DB tables: jobs, candidates, applications, taxonomy terms, term relations, term counts, plugin transients, and a parallel meta table.
* Front-end candidate and employer dashboards with a section-aware top bar.
* Job listings page with keyword search, sort, and sector / skills / job-type / experience / salary / location filters.
* Sector &rarr; skills cascading filter on listings, job-posting, candidate profile and admin metaboxes.
* Job-posting form (front-end and admin) with apply-method (internal / external / both) and full meta save.
* Application flow with private CV snapshots and a permission-checked download handler.
* Employer Job Applications dashboard section with bulk status changes.
* wp-admin pages: Workeera Dashboard (stats + recent applications), Job Applications, custom columns on Jobs and Candidates with employer + listing-status filters.
* Email notifications: job published, new application, application status — theme-overridable.
* WP-Cron: expire jobs, clean orphan applications, refresh term counts, purge stale transients.
* Theme-overridable templates and translation-ready strings throughout.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
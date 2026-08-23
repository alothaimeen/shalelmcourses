<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings for the admissions plugin.
 *
 * @package    local_shomokh_admissions
 * @copyright  2026 Shomokh Al-Elm
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['acceptfallback'] = 'Yes, join foundation';
$string['actions'] = 'Actions';
$string['addcourse'] = 'Add course';
$string['admindashboard'] = 'Admissions dashboard';
$string['admission'] = 'Diploma admissions';
$string['allprogramsready'] = 'All programs are fully configured and technically ready.';
$string['allstatuses'] = 'All statuses';
$string['alreadyenrolled'] = 'You are already enrolled in this program.';
$string['applicationduplicate'] = 'You already have an application for this program. Its current status is shown.';
$string['applicationreceived'] = 'Your application was received successfully.';
$string['applicationstatus'] = 'Your application status';
$string['applicationtitle'] = 'Admission application';
$string['availablecourses'] = 'Courses available to link ({$a})';
$string['batchname'] = 'Cohort label';
$string['cannotenable'] = 'Admissions cannot be enabled because one or more open programs are not ready.';
$string['capability:manageprograms'] = 'Manage admission programs and settings';
$string['capability:review'] = 'Review certificates and make admission decisions';
$string['capability:sync'] = 'Run recognition, reconciliation and retries';
$string['capability:viewreports'] = 'View the admissions dashboard and reports';
$string['certificate'] = 'Islamic qualification certificate';
$string['certificate_help'] = 'Upload one clear PDF, JPG or PNG file.';
$string['certificatefilecount'] = 'Upload exactly one certificate file.';
$string['certificateunavailable'] = 'No file is available for this application.';
$string['choosecourse'] = 'Choose a course or type to filter the list';
$string['chooseprogramtype'] = 'Choose the diploma you need';
$string['cohort'] = 'Linked Moodle cohort (optional)';
$string['configenabled'] = 'Enable admissions for students';
$string['configsaved'] = 'Settings saved.';
$string['confirmconditions'] = 'I have read the admission conditions and confirm that the information and attachment are accurate.';
$string['confirmconditionsinternal'] = 'I have read the admission conditions and want to join this pathway.';
$string['confirmconditionsrequired'] = 'You must confirm the admission conditions and accuracy of the information.';
$string['confirmremovecourse'] = 'Remove this course link from the program? The Moodle course will not be deleted.';
$string['courseadded'] = 'The course was linked.';
$string['courseremoved'] = 'The course link was removed without deleting the Moodle course.';
$string['coursegradeitem'] = 'Calculated result item';
$string['courses'] = 'Linked courses';
$string['dashboardfailed'] = 'Cases needing attention';
$string['dashboardpending'] = 'Applications awaiting review';
$string['dashboardtoday'] = 'Applications today';
$string['dashboardtotal'] = 'Total applications';
$string['decisionapprove'] = 'Accept qualification and add the pathway';
$string['decisionnote'] = 'Internal note';
$string['decisionreject'] = 'Do not accept qualification; offer foundation';
$string['declinefallback'] = 'No, not now';
$string['defaultfallback'] = 'Default foundation destination after qualification rejection';
$string['description'] = 'Program description';
$string['disabled'] = 'The admissions tool is not enabled.';
$string['editprogram'] = 'Edit program';
$string['error:enrolment'] = 'Your courses could not be prepared now. The system will retry automatically.';
$string['error:nofallback'] = 'No foundation fallback is configured. Please contact the administration team.';
$string['error:programunavailable'] = 'The selected program is not currently available for applications.';
$string['error:specialistalreadychosen'] = 'You already selected a specialist pathway. Follow its status before choosing another pathway.';
$string['externalrequired'] = 'We did not find a completed foundation level on this platform. If you hold an external Islamic qualification, upload it for review.';
$string['fallbackaccepted'] = 'Your consent was recorded and foundation courses are being prepared.';
$string['fallbackdeclined'] = 'Your choice was saved and your enrolments were not changed.';
$string['fallbackofferheading'] = 'Would you like to join the foundation diploma?';
$string['fallbackoffertext'] = 'The external qualification was not accepted for the specialist pathway. You will not be transferred without your consent.';
$string['filtercourseshelp'] = 'Open the list to see every available course, or type part of its full or short name to filter the results immediately.';
$string['filterstatus'] = 'Filter by status';
$string['foundationdefaultdescription'] = 'A structured foundation in creed, hadith, jurisprudence, biography and manners.';
$string['foundationtitle'] = 'Foundation Diploma in Islamic Studies';
$string['generalsettings'] = 'General settings';
$string['healthcheck'] = 'Readiness check';
$string['internaleligible'] = 'We verified that you completed a full foundation level on this platform. No certificate upload is required.';
$string['levelcompletiongradeitem'] = 'Internal level completion result';
$string['levelcompletiongradeitem_help'] = 'Select the grade item that proves completion of the whole level, such as “Third batch, level one — Course completion”. Linked courses below are enrolment destinations, not completion requirements.';
$string['levelcompletionthreshold'] = 'Passing result value';
$string['levelcompletionthreshold_help'] = 'The minimum value that means the full level was passed. For the current “Course completion” item, use 1.';
$string['introhtml'] = 'Welcome text';
$string['invalidcertificate'] = 'The file could not be accepted. Use a clear PDF, JPG or PNG within the allowed size.';
$string['invalidtransition'] = 'The application changed or this decision is no longer available. The page was refreshed to protect the data.';
$string['maxcertificatebytes'] = 'Maximum certificate file size';
$string['messageprovider:status_update'] = 'Admission application status update';
$string['mycourses'] = 'Go to my courses';
$string['noapplications'] = 'There are no matching applications.';
$string['noavailablecourses'] = 'There are no more courses available to link.';
$string['nocohort'] = 'No cohort';
$string['nocourses'] = 'No courses are linked yet.';
$string['nolevelcompletiongradeitem'] = 'No internal completion result for this program';
$string['notconfigured'] = 'This option is being prepared. Please try again later.';
$string['notify:approved'] = 'Your application was approved and your courses are being prepared.';
$string['notify:enrolled'] = 'Your courses are ready and you can begin studying.';
$string['notify:pending'] = 'Your application was received and is awaiting review.';
$string['notify:rejected'] = 'You can now review your application and choose the foundation diploma if you wish.';
$string['notify:subject'] = 'Shomokh admissions application update';
$string['notopen'] = 'Registration for this option is currently unavailable.';
$string['notready'] = 'Not ready';
$string['operationqueued'] = 'The retry was queued.';
$string['pluginname'] = 'Shomokh admissions and pathways';
$string['preparingcourses'] = 'Your courses are being prepared. You may close this page and return later.';
$string['previewclosedmessage'] = 'Admission programs are being prepared. The opening date will be announced later; no action is required now.';
$string['previewclosedtitle'] = 'Admissions are currently closed';
$string['previewdetails'] = 'View admission details';
$string['privacy:metadata'] = 'The admissions plugin stores student applications, review decisions and enrolment audit records.';
$string['privacy:metadata:applications'] = 'Academic admission applications.';
$string['privacy:metadata:audit'] = 'Application action audit trail.';
$string['privacy:metadata:certificate'] = 'The external qualification uploaded by the student.';
$string['privacy:metadata:decision'] = 'Review decision data.';
$string['privacy:metadata:program'] = 'The requested program or pathway.';
$string['privacy:metadata:status'] = 'The application status.';
$string['privacy:metadata:userid'] = 'The student user ID.';
$string['program'] = 'Program';
$string['programcode'] = 'Internal code';
$string['programenabled'] = 'Program enabled';
$string['programname'] = 'Display name';
$string['programreadiness'] = 'Program readiness';
$string['programs'] = 'Programs and pathways';
$string['programsaved'] = 'Program settings were saved.';
$string['programtype'] = 'Program type';
$string['programtype:foundation'] = 'Foundation';
$string['programtype:specialist'] = 'Specialist';
$string['publicpreview'] = 'Show the closed admissions page to students';
$string['publicpreview_help'] = 'Shows an informational card and page stating that admissions will be announced later. It accepts no applications or files and runs no recognition or enrolment.';
$string['readconditions'] = 'Please read the conditions carefully to make sure you are on the right pathway.';
$string['readiness:invalidthreshold'] = 'The level completion threshold is outside the grade item range.';
$string['readiness:missinggradeitem'] = 'The configured level completion grade item is missing or invalid; select it again.';
$string['readiness:missingcohort'] = 'The linked cohort no longer exists.';
$string['readiness:missingcourse'] = 'A linked course no longer exists.';
$string['readiness:nocourses'] = 'No courses are linked.';
$string['readiness:nofallback'] = 'There is no enabled default foundation destination.';
$string['readiness:nomanual'] = 'Course “{$a}” has no active manual enrolment instance.';
$string['readiness:nocompletionsource'] = 'Specialist pathways are open, but no valid internal foundation level result is configured.';
$string['readiness:telegram'] = 'The Telegram URL is missing or invalid.';
$string['readinessreport'] = 'Readiness report for all programs';
$string['ready'] = 'Ready';
$string['recognitionbatch'] = 'Students per scheduled recognition batch';
$string['recognizeexisting'] = 'Recognise students from existing enrolments';
$string['registrationopen'] = 'Applications open';
$string['removecourse'] = 'Remove link';
$string['requirements'] = 'Admission conditions';
$string['retentiondays'] = 'Certificate retention after final decision (days)';
$string['retrylimit'] = 'Maximum enrolment attempts';
$string['retryoperation'] = 'Retry enrolment';
$string['review'] = 'Review';
$string['reviewcertificate'] = 'View uploaded certificate';
$string['reviewdecision'] = 'Decision';
$string['reviewed'] = 'The decision was saved.';
$string['reviewqueue'] = 'Certificate review queue';
$string['reviewtitle'] = 'Review application: {$a}';
$string['runsync'] = 'Run recognition now';
$string['saveconfig'] = 'Save settings';
$string['saveprogram'] = 'Save program';
$string['savereview'] = 'Save decision';
$string['search'] = 'Search';
$string['searchcourses'] = 'Search by course name or short name';
$string['selectprogram'] = 'Select this pathway';
$string['source'] = 'Eligibility source';
$string['source:direct'] = 'Direct foundation application';
$string['source:external'] = 'External certificate';
$string['source:internal'] = 'Internal completion';
$string['source:recognized'] = 'Recognised existing enrolment';
$string['specialistdefaultdescription'] = 'Intensive pathways that establish the student in a selected discipline.';
$string['specialisttitle'] = 'Specialist Diplomas in Islamic Studies';
$string['startapplication'] = 'Apply for a diploma';
$string['state:publicpreview'] = 'Closed public display — no applications accepted';
$string['status'] = 'Status';
$string['status:approved_pending_enrolment'] = 'Your application was approved and courses are being prepared';
$string['status:enrolled'] = 'You were accepted and your courses were added';
$string['status:foundation_offer_accepted'] = 'Your foundation choice was accepted and courses are being prepared';
$string['status:foundation_offer_declined'] = 'You did not choose foundation enrolment';
$string['status:needs_attention'] = 'Your application needs attention from the admissions team';
$string['status:pending_review'] = 'Your certificate is awaiting review';
$string['status:recognized_existing'] = 'Your current enrolment was recognised';
$string['status:rejected_offer_foundation'] = 'The qualification was not accepted; you may choose the foundation diploma';
$string['status:unknown'] = 'Your application is being processed';
$string['statusintro'] = 'You can return to this page at any time to follow your application.';
$string['student'] = 'Student';
$string['studentmessage'] = 'Short message to the student';
$string['submitapplication'] = 'Submit application';
$string['submittedat'] = 'Submitted';
$string['synccomplete'] = 'Recognition completed. {$a} students were inspected.';
$string['syncconfirm'] = 'This will inspect enrolments for enabled recognition programs. Do you want to continue?';
$string['syncqueued'] = 'Student recognition started in batches.';
$string['syncunavailable'] = 'No enabled recognition program is currently available.';
$string['task:cleanupcertificates'] = 'Clean certificate files after the retention period';
$string['task:processenrolments'] = 'Process pending admission enrolments';
$string['task:recogniseexisting'] = 'Recognise students from their current enrolments';
$string['telegramchannel'] = 'Open the Telegram channel';
$string['telegramnotice'] = 'This link is for pathway students only. Please do not share it.';
$string['telegramurl'] = 'Telegram channel URL';
$string['track'] = 'Pathway';
$string['track:hadith'] = 'Hadith';
$string['track:none'] = 'No pathway';
$string['track:tafsir'] = 'Tafsir';
$string['viewoptions'] = 'View options';
$string['viewstatus'] = 'Follow admission application';
$string['welcomedefault'] = 'We are delighted to welcome you. Your first step in seeking Islamic knowledge starts here.';
$string['welcomeheading'] = 'Welcome to Shomokh Al-Elm';

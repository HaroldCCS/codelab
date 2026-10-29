<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for mod_codelab.
 *
 * @package   mod_codelab
 * @copyright 2026 ProyectoEditorMoodle
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']        = 'CodeLab - Code Editor';
$string['pluginadministration'] = 'CodeLab Administration';
$string['modulename']        = 'CodeLab';

// Capabilities (db/access.php).
$string['codelab:view'] = 'View CodeLab activity';
$string['codelab:submit'] = 'Submit code in CodeLab';
$string['codelab:grade'] = 'Grade CodeLab submissions';
$string['codelab:viewallsubmissions'] = 'View all CodeLab submissions';
$string['codelab:addinstance'] = 'Add a new CodeLab activity';
$string['codelab:execute'] = 'Run code in CodeLab';
$string['modulenameplural']  = 'CodeLabs';
$string['modulename_help']   = 'CodeLab allows students to write, run and submit code directly in Moodle. Teachers can define test cases for automatic grading.';

$string['codeeditor_settings']  = 'Editor Settings';
$string['default_language']     = 'Default language';
$string['languages_allowed']    = 'Allowed languages';
$string['languages_allowed_help'] = 'Select the programming languages students can use for this activity.';
$string['starter_code']         = 'Starter code';
$string['starter_code_help']    = 'Code shown in the editor when the student first opens the activity. Useful for providing a template or base structure.';
$string['runner_code']          = 'Runner code (auto wrapper)';
$string['runner_code_help']     = 'Code that automatically wraps the student\'s solution. Use {{CODE}} as a placeholder where the student\'s code will be inserted. Lets students only write their function/logic without needing input() or print().';
$string['execution_limits']     = 'Execution Limits';
$string['time_limit']           = 'Time limit per execution';
$string['memory_limit']         = 'Memory limit';
$string['seconds']              = '{$a} seconds';
$string['submission_settings']  = 'Submission Settings';
$string['max_attempts']         = 'Maximum attempts';
$string['unlimited']            = 'Unlimited';
$string['grading_method']       = 'Grading method';
$string['grading_best']         = 'Best attempt';
$string['grading_last']         = 'Last attempt';
$string['grading_first']        = 'First attempt';
$string['grading_average']      = 'Average of attempts';
$string['testcases_visible']    = 'Show test cases to students';
$string['testcases_visible_help'] = 'If enabled, students can see the name, input, and expected output of each test case. Cases marked as "hidden" are never visible.';
$string['allow_submissions_from_date'] = 'Allow submissions from';
$string['duedate']              = 'Due date';
$string['cutoffdate']           = 'Cut-off date (no submissions accepted after this date)';
$string['languages']            = 'Languages';

$string['testcases']            = 'Test Cases';
$string['testcase']             = 'Test case';
$string['add_testcase']         = '+ Add test case';
$string['tc_name']              = 'Name';
$string['tc_stdin']             = 'Input (stdin)';
$string['tc_expected_output']   = 'Expected output';
$string['tc_points']            = 'Points';
$string['tc_hidden']            = 'Hidden';
$string['hidden_testcase']      = 'Hidden case';

$string['last_submission']      = 'Last submission';
$string['grade']                = 'Grade';
$string['attempts']             = 'Attempts';
$string['pending']              = 'Pending';
$string['past_due']             = '⚠️ The due date has passed. You can still submit, but it may affect your grade.';
$string['before_allow_from']    = 'This activity is not open for submissions yet. You will be able to submit from {$a}.';
$string['past_cutoff']          = '❌ The activity is closed. No more submissions are accepted.';

$string['submissions']          = 'Submissions';
$string['statistics']           = 'Statistics';
$string['preview']              = 'Preview';
$string['student']              = 'Student';
$string['submitted']            = 'Submitted';
$string['language']             = 'Language';
$string['tests_passed']         = 'Tests passed';
$string['actions']              = 'Actions';
$string['view']                 = 'View';
$string['no_submissions_yet']   = 'No submissions yet for this activity.';
$string['preview_mode_notice']  = 'Preview mode: you are viewing the activity as a student.';
$string['total_students']       = 'Students';
$string['total_submissions']    = 'Total submissions';
$string['avg_grade']            = 'Average grade';
$string['pass_rate']            = 'Pass rate';
$string['pass_rate_unavailable'] = 'Not set';
$string['pass_rate_no_gradepass'] = 'The pass rate cannot be calculated because this activity has no grade to pass configured. Set it in the course gradebook (Grade item settings -> Grade to pass) and the percentage will appear here.';
$string['grade_saved']          = 'Grade saved. Grade recorded in the gradebook: {$a->grade} (method: {$a->method}).';

// Student and teacher interface (MDL-05: previously hardcoded in the source).
$string['unknown_user']         = 'Unknown user';
$string['runner_active_notice'] = '<strong>Simplified mode is on:</strong> you only write your function or logic; reading the input and printing the result happen automatically. You do not need <code>input()</code> or <code>print()</code>.';
$string['grade_manual_note']    = '(grade set manually by the teacher)';
$string['teacher_feedback']     = 'Teacher feedback';
$string['teacher_feedback_optional'] = 'Teacher feedback (optional)';
$string['run_and_test']         = 'Run and test';
$string['submit_solution']      = 'Submit';
$string['program_output']       = 'Program output';
$string['not_graded']           = 'Not graded';
$string['actual_output']        = 'Actual output';
$string['compile_error_label']  = 'Compilation error';
$string['stderr_label']         = 'Error output (stderr)';
$string['submitted_code']       = 'Submitted code';
$string['copy']                 = 'Copy';
$string['copied']               = 'Copied';
$string['manual_grade_label']   = 'Manual grade (leave empty to use the automatic one)';
$string['auto_grade_placeholder'] = 'Automatic grade: {$a}';
$string['comments']             = 'Comments';
$string['feedback_placeholder'] = 'Write feedback for the student...';
$string['save_grade']           = 'Save grade';
$string['tc_name_placeholder']  = 'e.g. Basic test';
$string['hide_from_student']    = 'Hide this case from the student';
$string['delete']               = 'Delete';
$string['runner_code_explain']  = '<strong>What is this for?</strong><br>If you fill it in, students <strong>only write their function or logic</strong> without needing <code>input()</code> or <code>print()</code>. Use <code>{{CODE}}</code> as the marker where the student code will be inserted.<br><br>If you leave it empty, the usual behaviour applies: the student handles input and output.';
$string['example_python']       = 'Example (Python)';
$string['student_writes_only']  = 'The student only writes:';
$string['testcase_pass_rates']  = 'Test case pass rates';
$string['passed']               = 'Passed';
$string['failed']               = 'Failed';

$string['invalidsubmission']    = 'The requested submission does not exist or does not belong to this activity.';
$string['reset_submissions']    = 'Delete all submissions, their results and their grades';

$string['language_not_allowed'] = 'The selected language is not allowed in this activity.';
$string['code_too_large']       = 'The code is too large (maximum 64KB).';
$string['no_testcases']         = 'This activity has no test cases configured.';
$string['max_attempts_reached'] = 'You have reached the maximum number of allowed attempts.';
$string['submission_saved']     = 'Your submission has been successfully recorded!';
$string['executor_connection_error'] = 'Could not connect to the code execution server. Please contact your administrator.';
$string['executor_timeout']     = 'The execution server took too long to respond. Please try again later.';
$string['executor_not_configured'] = 'The code execution server is not configured. An administrator must set the URL of the self-hosted Judge0 server in the CodeLab settings.';

$string['admin_settings']       = 'CodeLab Settings';
$string['judge0_api_url']       = 'Judge0 API URL';
$string['judge0_api_url_desc']  = 'Base URL of your self-hosted Judge0 server (e.g. http://judge0_server:2358 with the local overlay, or http://YOUR_JUDGE0_HOST:2358).';
$string['judge0_api_key']       = 'Judge0 API Key';
$string['judge0_api_key_desc']  = 'Authentication token (X-Auth-Token) if your server requires one. Leave empty for a self-hosted server without a token.';

// AI recommendations — admin settings.
$string['ai_settings']       = 'AI recommendations';
$string['ai_settings_desc']  = 'Generate qualitative feedback on the student code after each submission. These recommendations NEVER change the grade.';
$string['ai_enabled']        = 'Enable AI recommendations';
$string['ai_enabled_desc']   = 'Master switch. If disabled, no activity can generate recommendations even if the teacher requests it.';
$string['ai_provider']       = 'AI provider';
$string['ai_provider_desc']  = 'Service that will generate the recommendations. You need a valid API key for the selected provider.';
$string['ai_provider_claude'] = 'Claude (Anthropic)';
$string['ai_provider_gemini'] = 'Gemini (Google)';
$string['ai_provider_openai'] = 'OpenAI';
$string['ai_api_key']        = 'API key';
$string['ai_api_key_desc']   = 'Authentication key for the selected provider. Stored masked.';
$string['ai_model']          = 'Model (optional)';
$string['ai_model_desc']     = 'Model name to use. Leave empty to use a sensible default model for the provider.';

// AI recommendations — per-activity settings.
$string['ai_feedback_section']       = 'AI recommendations';
$string['ai_disabled_site_notice']   = 'AI recommendations are disabled site-wide. An administrator must enable them and configure a provider in the CodeLab settings.';
$string['ai_feedback_enabled']       = 'Generate AI recommendations';
$string['ai_feedback_enabled_label'] = 'Enable for this activity';
$string['ai_feedback_enabled_help']  = 'If enabled, upon submission a qualitative feedback about the code is generated based on the criteria you select. The grade is computed only from the test cases; the AI never changes it.';
$string['ai_criteria']       = 'Criteria to evaluate';
$string['ai_criteria_help']  = 'Select which qualitative aspects the AI should comment on. Only the checked criteria are evaluated.';
$string['ai_criterion_readability']    = 'Readability and style';
$string['ai_criterion_efficiency']     = 'Efficiency and complexity';
$string['ai_criterion_best_practices'] = 'Language best practices';
$string['ai_criterion_naming']         = 'Variable and function naming';
$string['ai_criterion_error_handling'] = 'Error handling and edge cases';
$string['ai_criterion_modularity']     = 'Modularity and structure';

// AI recommendations — interface and messages.
$string['ai_feedback_title']      = 'AI recommendations';
$string['ai_feedback_disclaimer'] = 'Automatically generated qualitative suggestions. They do not affect your grade.';
$string['ai_connection_error']    = 'Could not reach the AI service. Your submission and grade were saved correctly.';
$string['ai_response_error']      = 'The AI service returned an invalid response. Your submission and grade were saved correctly.';

$string['privacy:metadata:codelab_submissions']              = 'Student code submissions.';
$string['privacy:metadata:codelab_submissions:userid']       = 'The ID of the user who made the submission.';
$string['privacy:metadata:codelab_submissions:code']         = 'The source code submitted by the student.';
$string['privacy:metadata:codelab_submissions:language']     = 'Programming language used.';
$string['privacy:metadata:codelab_submissions:grade']        = 'Grade obtained.';
$string['privacy:metadata:codelab_submissions:timecreated']  = 'Submission creation date.';
$string['privacy:metadata:codelab_submissions:timemodified'] = 'Last modification date.';
$string['privacy:metadata:judge0_api']       = 'Student code and standard input are sent to the external Judge0 server for execution.';
$string['privacy:metadata:judge0_api:code']  = 'The source code being executed.';
$string['privacy:metadata:judge0_api:stdin'] = 'The standard input provided to the program.';
$string['privacy:metadata:ai_api']            = 'If the teacher enables AI recommendations, the submitted code and the exercise statement are sent to the configured AI provider to generate qualitative feedback.';
$string['privacy:metadata:ai_api:code']       = 'The source code submitted by the student.';
$string['privacy:metadata:ai_api:statement']  = 'The exercise statement.';
$string['privacy:metadata:codelab_submissions:ai_feedback'] = 'AI-generated qualitative recommendation for the submission.';
$string['privacy:metadata:codelab_submissions:attempt_number']     = 'Attempt number of the submission.';
$string['privacy:metadata:codelab_submissions:status']             = 'Submission status (draft, submitted or graded).';
$string['privacy:metadata:codelab_submissions:grade_override']     = 'Grade manually set by the teacher.';
$string['privacy:metadata:codelab_submissions:feedback']           = 'Feedback written by the teacher about the submission.';
$string['privacy:metadata:codelab_submissions:ai_feedback_status'] = 'Status of the AI recommendation generation.';
$string['privacy:metadata:codelab_submissions:ai_feedback_time']   = 'Date when the AI recommendation was generated.';
$string['privacy:metadata:codelab_submissions:tests_passed']       = 'Number of test cases passed.';
$string['privacy:metadata:codelab_submissions:tests_total']        = 'Total number of test cases evaluated.';

$string['privacy:metadata:codelab_submission_results']                = 'Result of each test case for the student submissions.';
$string['privacy:metadata:codelab_submission_results:status']         = 'Test case result (passed, failed, error, timeout or compile error).';
$string['privacy:metadata:codelab_submission_results:actual_output']  = 'The output produced by the program written by the student.';
$string['privacy:metadata:codelab_submission_results:stderr']         = 'The error output produced by the program written by the student.';
$string['privacy:metadata:codelab_submission_results:compile_output'] = 'Compiler messages produced while processing the student code.';
$string['privacy:metadata:codelab_submission_results:execution_time'] = 'Time the code took to run, in seconds.';
$string['privacy:metadata:codelab_submission_results:memory_used']    = 'Memory consumed by the code execution, in kilobytes.';
$string['privacy:metadata:codelab_submission_results:timecreated']    = 'Date when the test case result was recorded.';
$string['privacy:path:submissions']          = 'Code submissions';

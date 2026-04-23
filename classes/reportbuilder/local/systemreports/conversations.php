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

namespace mod_dialogue\reportbuilder\local\systemreports;

use core_reportbuilder\system_report;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use html_writer;
use lang_string;
use moodle_url;

/**
 * System report for searching dialogue conversations.
 *
 * Provides searchable/filterable columns for participant name, subject,
 * message content, attachments, status, and date.
 *
 * @package   mod_dialogue
 * @copyright 2025 Andrew Rowatt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class conversations extends system_report {

    /**
     * Initialise the report: set the main table, joins, base conditions,
     * columns, filters and default sort order.
     */
    protected function initialise(): void {
        global $USER;

        $dialogueid = $this->get_parameter('dialogueid', 0, PARAM_INT);
        $cmid       = $this->get_parameter('cmid', 0, PARAM_INT);

        // dialogue_conversations is the one-row-per-conversation table.
        $this->set_main_table('dialogue_conversations', 'dc');

        // Register the entity names used by columns and filters in this report.
        // These map to the SQL table aliases set up by set_main_table() and add_join().
        $this->annotate_entity('dc', new lang_string('conversation', 'dialogue'));
        $this->annotate_entity('dm', new lang_string('message', 'dialogue'));
        $this->annotate_entity('u', new lang_string('user'));

        // Always restrict to the current dialogue instance.
        // add_base_condition_simple generates a reportbuilder-safe parameter name internally.
        $this->add_base_condition_simple('dc.dialogueid', $dialogueid);

        // Join the latest open/closed message for each conversation using a
        // correlated subquery so no extra named parameter is needed in the JOIN.
        $this->add_join(
            "JOIN {dialogue_messages} dm
                ON dm.conversationid = dc.id
               AND dm.conversationindex = (
                       SELECT MAX(dm2.conversationindex)
                         FROM {dialogue_messages} dm2
                        WHERE dm2.conversationid = dc.id
                          AND dm2.state IN ('open', 'closed')
                   )"
        );

        // Join the author of the latest message.
        $this->add_join("JOIN {user} u ON u.id = dm.authorid");

        // Non-privileged users only see conversations they participate in.
        if (!has_capability('mod/dialogue:viewany', $this->get_context())) {
            // database::generate_param_name() produces the rbparam-prefixed names
            // required by the reportbuilder's validate_params() check.
            $visuserparam = database::generate_param_name();
            $this->add_base_condition_sql(
                "dc.id IN (
                    SELECT dp.conversationid
                      FROM {dialogue_participants} dp
                     WHERE dp.userid = :{$visuserparam})",
                [$visuserparam => $USER->id]
            );
        }

        $this->add_columns($cmid);
        $this->add_filters();

        $this->set_initial_sort_column('dm:timemodified', SORT_DESC);
    }

    /**
     * Define the report columns.
     *
     * @param int $cmid Course-module ID used to build conversation links.
     */
    protected function add_columns(int $cmid): void {
        global $DB;

        // Participant full name.
        $this->add_column(
            (new column('fullname', new lang_string('fullname', 'dialogue'), 'u'))
                ->set_type(column::TYPE_TEXT)
                ->add_field($DB->sql_fullname('u.firstname', 'u.lastname'), 'fullname')
                ->set_is_sortable(true)
        );

        // Participant username.
        $this->add_column(
            (new column('username', new lang_string('username'), 'u'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('u.username', 'username')
                ->set_is_sortable(true)
        );

        // Conversation subject (linked to the conversation view).
        $this->add_column(
            (new column('subject', new lang_string('subject', 'dialogue'), 'dc'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dc.subject', 'subject')
                ->add_field('dc.id', 'convid')
                ->set_is_sortable(true, ['dc.subject'])
                ->add_callback(
                    static function (?string $subject, \stdClass $row) use ($cmid): string {
                        $url = new moodle_url('/mod/dialogue/conversation.php', [
                            'id'             => $cmid,
                            'conversationid' => $row->convid,
                        ]);
                        $label = $subject !== null && $subject !== ''
                            ? $subject
                            : get_string('nosubject', 'dialogue');
                        return html_writer::link($url, $label);
                    }
                )
        );

        // Latest message body (plain-text excerpt, 100 chars).
        $this->add_column(
            (new column('body', new lang_string('message', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dm.body', 'body')
                ->add_field('dm.bodyformat', 'bodyformat')
                ->set_is_sortable(false)
                ->add_callback(
                    static function (?string $body, \stdClass $row): string {
                        if (empty($body)) {
                            return '';
                        }
                        return shorten_text(
                            strip_tags(format_text($body, $row->bodyformat)),
                            100
                        );
                    }
                )
        );

        // Has attachments (boolean).
        $this->add_column(
            (new column('attachments', new lang_string('attachments', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_BOOLEAN)
                ->add_field('dm.attachments', 'attachments')
                ->set_is_sortable(true)
                ->add_callback(static function ($value): bool {
                    return (bool) $value;
                })
        );

        // Conversation state (open / closed).
        $this->add_column(
            (new column('state', new lang_string('status', 'dialogue'), 'dm'))
                ->set_type(column::TYPE_TEXT)
                ->add_field('dm.state', 'state')
                ->set_is_sortable(true)
                ->add_callback(static function (?string $state): string {
                    if (empty($state)) {
                        return '';
                    }
                    return get_string($state, 'dialogue');
                })
        );

        // Date of last activity.
        $this->add_column(
            (new column('timemodified', new lang_string('date'), 'dm'))
                ->set_type(column::TYPE_TIMESTAMP)
                ->add_field('dm.timemodified', 'timemodified')
                ->set_is_sortable(true)
                ->add_callback(static function (?int $value): string {
                    if ($value === null || $value === 0) {
                        return '';
                    }
                    return userdate($value);
                })
        );
    }

    /**
     * Define the report filters.
     */
    protected function add_filters(): void {
        global $DB;

        // Filter by participant full name.
        $this->add_filter(
            new filter(
                text::class,
                'fullname',
                new lang_string('fullname', 'dialogue'),
                'u',
                $DB->sql_fullname('u.firstname', 'u.lastname')
            )
        );

        // Filter by participant username.
        $this->add_filter(
            new filter(
                text::class,
                'username',
                new lang_string('username'),
                'u',
                'u.username'
            )
        );

        // Filter by subject / topic.
        $this->add_filter(
            new filter(
                text::class,
                'subject',
                new lang_string('subject', 'dialogue'),
                'dc',
                'dc.subject'
            )
        );

        // Filter by message content.
        $this->add_filter(
            new filter(
                text::class,
                'body',
                new lang_string('message', 'dialogue'),
                'dm',
                'dm.body'
            )
        );

        // Filter by whether the conversation has attachments.
        $this->add_filter(
            new filter(
                boolean_select::class,
                'attachments',
                new lang_string('attachments', 'dialogue'),
                'dm',
                'CASE WHEN dm.attachments > 0 THEN 1 ELSE 0 END'
            )
        );

        // Filter by conversation state (open / closed).
        $this->add_filter(
            (new filter(
                select::class,
                'state',
                new lang_string('status', 'dialogue'),
                'dm',
                'dm.state'
            ))->set_options([
                \mod_dialogue\dialogue::STATE_OPEN   => get_string('open', 'dialogue'),
                \mod_dialogue\dialogue::STATE_CLOSED => get_string('closed', 'dialogue'),
            ])
        );

        // Filter by date of last modification.
        $this->add_filter(
            new filter(
                date::class,
                'timemodified',
                new lang_string('date'),
                'dm',
                'dm.timemodified'
            )
        );
    }

    /**
     * Only users who can view any conversation in this dialogue may access this report.
     * Row-level visibility for non-privileged users is handled in initialise() via
     * SQL conditions on dialogue_participants; can_view() provides the page-level gate.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('mod/dialogue:viewany', $this->get_context());
    }
}

export interface ApiField {
    name: string;
    type: string;
    rule: string;
    description: string;
}

const modes =
    'single_elimination (one loss eliminates a player), double_elimination (two losses), round_robin (players face each other), swiss (pairings follow standings each round), or free_for_all (multiple players per heat).';

const tournamentRequest: ApiField[] = [
    {
        name: 'name',
        type: 'string',
        rule: 'Required',
        description: 'Tournament display name. Maximum 255 characters. Required for both POST and PUT.',
    },
    { name: 'mode_type', type: 'string', rule: 'Required', description: `Competition format: ${modes} Cannot change after the tournament starts.` },
    { name: 'description', type: 'string | null', rule: 'Optional', description: 'Tournament description, up to 1,000 characters.' },
    {
        name: 'start_at',
        type: 'date string | null',
        rule: 'Optional',
        description: 'Scheduled start date and time. Include a timezone, for example 2026-10-18T09:00:00+00:00.',
    },
    {
        name: 'end_at',
        type: 'date string | null',
        rule: 'Optional',
        description: 'Scheduled end date and time; must be after start_at. Scheduling does not start or end the tournament.',
    },
    {
        name: 'require_check_in',
        type: 'boolean',
        rule: 'Optional',
        description: 'Whether participant check-in is required. Defaults to false on creation.',
    },
    { name: 'check_in_time', type: 'integer | null', rule: 'Optional', description: 'Configured check-in time value; at least 1 when supplied.' },
    { name: 'max_entry', type: 'integer | null', rule: 'Optional', description: 'Configured participant limit; at least 2 when supplied.' },
    {
        name: 'split_participant',
        type: 'boolean | null',
        rule: 'Optional',
        description: 'Double elimination option that starts the lower half of the field in the lower bracket. Defaults to false.',
    },
    { name: 'ffa_heat_size', type: 'integer | null', rule: 'Required for free_for_all', description: 'Number of participants per heat; at least 2.' },
    {
        name: 'ffa_advance_count',
        type: 'integer | null',
        rule: 'Required for free_for_all',
        description: 'Number advancing from each heat; at least 1 and less than ffa_heat_size.',
    },
    {
        name: 'head_to_head_count',
        type: 'integer | null',
        rule: 'Required for round_robin',
        description: 'Configured number of head-to-head meetings; at least 1.',
    },
    { name: 'rank_by', type: 'string | null', rule: 'Optional', description: 'Ranking configuration string, up to 50 characters.' },
    ...['points_per_match_win', 'points_per_match_tie', 'points_per_set_win', 'points_per_set_tie', 'points_per_bye'].map((name) => ({
        name,
        type: 'number | null',
        rule: 'Required for swiss',
        description: `Points awarded for ${name.replace('points_per_', '').replaceAll('_', ' ')}. Must be zero or greater; decimals are allowed.`,
    })),
    {
        name: 'swiss_rounds',
        type: 'integer | null',
        rule: 'Required for swiss',
        description:
            'Total Swiss rounds to play; at least 1. Updating this value on a started tournament triggers generation of the next round if the current round is complete. Cannot be reduced below rounds already generated or changed after the tournament ends.',
    },
];

const tournamentResponse: ApiField[] = [
    { name: 'id', type: 'integer', rule: 'Read-only', description: 'Internal database ID.' },
    {
        name: 'open_id',
        type: 'string',
        rule: 'Read-only',
        description: 'Generated public tournament ID. Use this value as {openId} in endpoint paths.',
    },
    ...tournamentRequest.map((field) => ({
        ...field,
        rule:
            ['split_participant', 'ffa_heat_size', 'ffa_advance_count', 'head_to_head_count', 'rank_by', 'swiss_rounds'].includes(field.name) ||
            field.name.startsWith('points_per_')
                ? 'Conditional'
                : 'Returned',
        description: field.description,
    })),
    {
        name: 'status',
        type: 'string | null',
        rule: 'Read-only',
        description:
            'Lifecycle state: pending, open, closed, seeding, started, or ended. New tournaments always start as pending. POST/PUT configuration requests cannot set this field; use the start/end action endpoints.',
    },
    {
        name: 'players_count / matches_count',
        type: 'integer',
        rule: 'Conditional',
        description: 'Participant and generated match totals. Included by the tournament list and detail endpoints.',
    },
    {
        name: 'created_at / updated_at',
        type: 'date string | null',
        rule: 'Read-only',
        description: 'Record creation and last-update timestamps in ISO 8601 format.',
    },
];

const participantResponse: ApiField[] = [
    {
        name: 'id',
        type: 'integer',
        rule: 'Read-only',
        description: 'Participant ID. Use as {playerId} in participant paths and as winner_id in score requests.',
    },
    { name: 'name', type: 'string', rule: 'Returned', description: 'Participant display name.' },
    { name: 'tournament_id', type: 'integer', rule: 'Read-only', description: 'Internal ID of the tournament this participant belongs to.' },
    {
        name: 'seed',
        type: 'integer | null',
        rule: 'Returned',
        description: 'Assigned tournament seed. Participants are reseeded when players are removed.',
    },
    { name: 'checked_in', type: 'boolean', rule: 'Returned', description: 'Whether this participant is checked in.' },
    {
        name: 'checked_in_at',
        type: 'date string | null',
        rule: 'Returned',
        description: 'Check-in timestamp, or null when no timestamp is recorded.',
    },
    {
        name: 'final_rank',
        type: 'integer | null',
        rule: 'Read-only',
        description: 'Final placement, when assigned; null before a final rank is available.',
    },
];

const matchResponse: ApiField[] = [
    { name: 'id / tournament_id', type: 'integer', rule: 'Read-only', description: 'Match ID (use as {matchId}) and owning tournament database ID.' },
    {
        name: 'state',
        type: 'string | null',
        rule: 'Returned',
        description: 'Match state: pending, open, close, or complete. The literal value is close, not closed.',
    },
    {
        name: 'player1_id / player2_id',
        type: 'integer | null',
        rule: 'Returned',
        description: 'Participant IDs in a head-to-head match. A null slot can represent an unresolved pairing or a bye.',
    },
    {
        name: 'player1_score / player2_score',
        type: 'decimal string | null',
        rule: 'Returned',
        description: 'Recorded match scores; null before scores are available.',
    },
    {
        name: 'winner_id / loser_id',
        type: 'integer | null',
        rule: 'Returned',
        description: 'Participant IDs for the decided winner and loser, or null when not assigned.',
    },
    {
        name: 'is_tie',
        type: 'boolean | null',
        rule: 'Returned',
        description: 'Whether the match ended in a tie. Ties are supported for round_robin and swiss.',
    },
    {
        name: 'round',
        type: 'integer',
        rule: 'Returned',
        description: 'Bracket round number. Negative rounds denote the lower bracket in double elimination.',
    },
    { name: 'suggested_play_order', type: 'integer', rule: 'Returned', description: 'Suggested match scheduling order, starting at 1.' },
    {
        name: 'completed_at',
        type: 'date string | null',
        rule: 'Returned',
        description: 'Completion timestamp, or null if no completion time is recorded.',
    },
    {
        name: 'player1_prereq_match_id / player2_prereq_match_id',
        type: 'integer | null',
        rule: 'Returned',
        description: 'Earlier matches that supply each participant slot.',
    },
    {
        name: 'player1_is_prereq_match_loser / player2_is_prereq_match_loser',
        type: 'boolean',
        rule: 'Returned',
        description: 'True when a slot takes the loser of its prerequisite match rather than the winner.',
    },
    {
        name: 'scores_csv',
        type: 'string',
        rule: 'Conditional',
        description: 'Set scores in player1-player2 order, separated by commas, for example 6-4,4-6,7-5. Empty when no set scores exist.',
    },
    {
        name: 'player1 / player2',
        type: 'object | null',
        rule: 'Conditional',
        description: 'Nested participant objects with the participant fields described on participant endpoints.',
    },
    {
        name: 'participants',
        type: 'array',
        rule: 'Conditional',
        description:
            'Free-for-all slots: id, position, score, rank, is_winner, and nested player. Empty for head-to-head matches; omitted when the relation is not loaded.',
    },
];

export function getEndpointFields(method: string, path: string): { request: ApiField[]; response: ApiField[]; responseDescription: string } {
    if (path.endsWith('/actions/rollback')) {
        return {
            request: [{ name: 'status', type: 'string', rule: 'Required: started or ended', description: 'Expected current status. Must match the tournament; rollback moves it back one step.' }],
            response: tournamentResponse,
            responseDescription: '200 OK. data is the tournament with its updated status. Invalid or stale status returns 422 without changing data.',
        };
    }
    if (path.includes('/matches/preview')) {
        return {
            request: [],
            response: [
                {
                    name: 'data[round]',
                    type: 'array',
                    rule: 'Returned',
                    description:
                        'Preview matches grouped by round number. Each entry has round, suggested_play_order, player1, and player2; free-for-all previews use participants.',
                },
                {
                    name: 'player1 / player2 / participants[]',
                    type: 'object',
                    rule: 'Returned',
                    description:
                        'Preview slots describe a player or placeholder using type, id, name, and seed as applicable. These are previews and have no persisted match ID.',
                },
            ],
            responseDescription:
                '200 OK. data contains the preview grouped by round, or an empty array after start/end. meta.count is the number of preview matches.',
        };
    }
    if (path.includes('/matches')) {
        const scoring = path.endsWith('/score');
        return {
            request: scoring
                ? [
                      {
                          name: 'scores_csv',
                          type: 'string',
                          rule: 'Required',
                          description:
                              'Comma-separated set scores in player1-player2 order, for example 6-4,4-6,7-5. Score edits are rejected after tournament end.',
                      },
                      {
                          name: 'winner_id',
                          type: 'integer | "tie" | null',
                          rule: 'Optional',
                          description:
                              'ID of player1 or player2, or "tie" for round_robin/swiss only. Omit or send null to leave the winner unspecified.',
                      },
                  ]
                : [],
            response: matchResponse,
            responseDescription: `${path.endsWith('/generate') ? '201 Created' : '200 OK'}. data is ${scoring ? 'the updated match object' : 'an array of match objects with meta.count'}. Swiss generation appends the next round only after the current round is complete.`,
        };
    }
    if (path.includes('/participants')) {
        if (method === 'DELETE') {
            const bulk = !path.includes('{playerId}');
            return {
                request: bulk
                    ? [
                          {
                              name: 'player_ids',
                              type: 'integer[]',
                              rule: 'Required',
                              description:
                                  'Nonempty array of distinct participant IDs belonging to this tournament. Deletion is blocked after start/end.',
                          },
                      ]
                    : [],
                response: bulk ? [{ name: 'deleted_count', type: 'integer', rule: 'Returned', description: 'Number of participants removed.' }] : [],
                responseDescription: bulk
                    ? '200 OK. data contains deleted_count.'
                    : '204 No Content. No JSON response body. Deletion after start/end returns 409 Conflict.',
            };
        }
        if (path.endsWith('/process-check-ins')) {
            return {
                request: [],
                response: [],
                responseDescription: '200 OK. message confirms unchecked participants were removed and seeds updated; data is null.',
            };
        }
        const checkIn = path.endsWith('/check-in');
        return {
            request: checkIn
                ? [
                      {
                          name: 'checked_in',
                          type: 'boolean',
                          rule: 'Required',
                          description: 'true to check in, false to undo check-in. Cannot change after the tournament starts.',
                      },
                  ]
                : method === 'POST'
                  ? [
                        {
                            name: 'name',
                            type: 'string',
                            rule: 'Required',
                            description: 'Participant display name; maximum 255 characters. Players can only be added before tournament start.',
                        },
                        {
                            name: 'checked_in',
                            type: 'boolean',
                            rule: 'Optional',
                            description: 'Initial check-in state. Defaults to true in the API when omitted.',
                        },
                        {
                            name: 'checked_in_at',
                            type: 'date string | null',
                            rule: 'Optional',
                            description: 'Initial check-in timestamp; defaults to null.',
                        },
                    ]
                  : [],
            response: participantResponse,
            responseDescription: `${method === 'POST' ? '201 Created' : '200 OK'}. data is ${method === 'GET' ? 'an array of participants with meta.count' : 'the participant object'}.`,
        };
    }
    return {
        request: ['POST', 'PUT'].includes(method) && !path.includes('/actions/') ? tournamentRequest : [],
        response: tournamentResponse,
        responseDescription: `${method === 'POST' && path === '/tournaments' ? '201 Created' : '200 OK'}. data is ${method === 'GET' && path === '/tournaments' ? 'an array of tournament objects with meta.count; this API list is not paginated' : 'the tournament object'}. Mode-specific fields are conditional; counts appear only on list/detail responses.`,
    };
}

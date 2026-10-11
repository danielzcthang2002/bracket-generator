import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Braces, Check, Clipboard, ExternalLink, Menu, Trophy, X } from 'lucide-react';
import { useState } from 'react';

type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface Endpoint {
    method: Method;
    path: string;
    description: string;
    body?: string;
    response?: string;
}

interface Section {
    id: string;
    title: string;
    description: string;
    endpoints: Endpoint[];
}

const sections: Section[] = [
    {
        id: 'tournaments',
        title: 'Tournaments',
        description: 'Create tournaments and retrieve their configuration and lifecycle state.',
        endpoints: [
            {
                method: 'GET',
                path: '/tournaments',
                description: 'List all tournaments. Returns counts for participants and matches.',
                response: `{
  "message": "Tournaments retrieved successfully.",
  "data": [
    {
      "id": 12,
      "open_id": "3XK9M2PQ",
      "name": "Autumn Open",
      "mode_type": "single_elimination",
      "description": "Community championship",
      "start_at": "2026-10-18T09:00:00+00:00",
      "end_at": null,
      "require_check_in": true,
      "check_in_time": 30,
      "max_entry": 32,
      "status": "pending",
      "players_count": 16,
      "matches_count": 0,
      "created_at": "2026-10-11T03:30:00+00:00",
      "updated_at": "2026-10-11T03:30:00+00:00"
    }
  ],
  "meta": { "count": 1 }
}`,
            },
            {
                method: 'POST',
                path: '/tournaments',
                description: 'Create a tournament.',
                body: `{
  "name": "Autumn Open",
  "mode_type": "single_elimination",
  "description": "Community championship",
  "require_check_in": true,
  "check_in_time": 30,
  "max_entry": 32
}`,
                response: `{
  "message": "Tournament created successfully.",
  "data": {
    "id": 12,
    "open_id": "3XK9M2PQ",
    "name": "Autumn Open",
    "mode_type": "single_elimination",
    "status": "pending"
  }
}`,
            },
            {
                method: 'GET',
                path: '/tournaments/{openId}',
                description: 'Retrieve one tournament by its public ID.',
                response: `{
  "message": "Tournament retrieved successfully.",
  "data": {
    "id": 12,
    "open_id": "3XK9M2PQ",
    "name": "Autumn Open",
    "mode_type": "single_elimination",
    "description": "Community championship",
    "start_at": "2026-10-18T09:00:00+00:00",
    "end_at": null,
    "require_check_in": true,
    "check_in_time": 30,
    "max_entry": 32,
    "status": "pending",
    "players_count": 16,
    "matches_count": 0,
    "created_at": "2026-10-11T03:30:00+00:00",
    "updated_at": "2026-10-11T03:30:00+00:00"
  }
}`,
            },
            {
                method: 'PUT',
                path: '/tournaments/{openId}',
                description: 'Replace a tournament’s editable configuration.',
                body: `{
  "name": "Autumn Open Finals",
  "mode_type": "single_elimination",
  "require_check_in": true,
  "max_entry": 16
}`,
            },
            { method: 'POST', path: '/tournaments/{openId}/actions/start', description: 'Start the tournament and initialize its bracket.' },
            { method: 'POST', path: '/tournaments/{openId}/actions/end', description: 'End a tournament after its matches are complete.' },
        ],
    },
    {
        id: 'participants',
        title: 'Participants',
        description: 'Manage the field, check-in state, and tournament seeds.',
        endpoints: [
            {
                method: 'GET',
                path: '/tournaments/{openId}/participants',
                description: 'List participants in seed order.',
                response: `{
  "message": "Participants retrieved successfully.",
  "data": [
    {
      "id": 42,
      "name": "Northstar",
      "tournament_id": 12,
      "seed": 1,
      "checked_in": true,
      "checked_in_at": "2026-10-18T08:32:00+00:00",
      "final_rank": null
    }
  ],
  "meta": { "count": 1 }
}`,
            },
            {
                method: 'POST',
                path: '/tournaments/{openId}/participants',
                description: 'Add a participant.',
                body: `{
  "name": "Northstar",
  "checked_in": false
}`,
            },
            {
                method: 'DELETE',
                path: '/tournaments/{openId}/participants/{playerId}',
                description: 'Remove a participant before the tournament starts. Returns 204 with no body.',
            },
            {
                method: 'DELETE',
                path: '/tournaments/{openId}/participants',
                description: 'Remove multiple participants before the tournament starts.',
                body: `{
  "player_ids": [42, 57, 63]
}`,
                response: `{
  "message": "Participants deleted successfully.",
  "data": { "deleted_count": 3 }
}`,
            },
            {
                method: 'PATCH',
                path: '/tournaments/{openId}/participants/{playerId}/check-in',
                description: 'Set the participant’s check-in state.',
                body: `{
  "checked_in": true
}`,
            },
            {
                method: 'POST',
                path: '/tournaments/{openId}/participants/process-check-ins',
                description: 'Remove unchecked participants and reseed the remaining field.',
            },
        ],
    },
    {
        id: 'matches',
        title: 'Matches',
        description: 'Generate brackets, read match state, and report results.',
        endpoints: [
            {
                method: 'GET',
                path: '/tournaments/{openId}/matches',
                description: 'List generated matches without changing tournament state.',
                response: `{
  "message": "Matches retrieved successfully.",
  "data": [
    {
      "id": 84,
      "tournament_id": 12,
      "state": "open",
      "player1_id": 42,
      "player2_id": 57,
      "player1_score": null,
      "player2_score": null,
      "winner_id": null,
      "loser_id": null,
      "is_tie": false,
      "round": 1,
      "suggested_play_order": 1,
      "completed_at": null,
      "scores_csv": "",
      "player1": { "id": 42, "name": "Northstar", "seed": 1 },
      "player2": { "id": 57, "name": "Wildcats", "seed": 8 },
      "participants": []
    }
  ],
  "meta": { "count": 1 }
}`,
            },
            {
                method: 'POST',
                path: '/tournaments/{openId}/matches/generate',
                description: 'Generate or refresh matches using the tournament’s configured format.',
            },
            {
                method: 'PUT',
                path: '/tournaments/{openId}/matches/{matchId}/score',
                description: 'Record set scores and the winner. Use “tie” only for Round Robin or Swiss.',
                body: `{
  "scores_csv": "6-4,4-6,7-5",
  "winner_id": 42
}`,
            },
        ],
    },
];

const methodStyles: Record<Method, string> = {
    GET: 'border-sky-400/25 bg-sky-400/10 text-sky-300',
    POST: 'border-lime-400/25 bg-lime-400/10 text-lime-300',
    PUT: 'border-amber-400/25 bg-amber-400/10 text-amber-300',
    PATCH: 'border-violet-400/25 bg-violet-400/10 text-violet-300',
    DELETE: 'border-red-400/25 bg-red-400/10 text-red-300',
};

function CodeBlock({ code }: { code: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(code);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1600);
    };

    return (
        <div className="relative overflow-hidden rounded-xl border border-white/10 bg-[#0b0e09]">
            <button
                type="button"
                onClick={copy}
                className="absolute top-2.5 right-2.5 rounded-lg p-2 text-white/35 transition hover:bg-white/10 hover:text-white"
                aria-label="Copy code"
            >
                {copied ? <Check className="size-3.5 text-lime-300" /> : <Clipboard className="size-3.5" />}
            </button>
            <pre className="overflow-x-auto p-4 pr-12 text-xs leading-6 text-white/70">
                <code>{code}</code>
            </pre>
        </div>
    );
}

function EndpointCard({ endpoint }: { endpoint: Endpoint }) {
    return (
        <article className="overflow-hidden rounded-2xl border border-black/8 bg-white/70 dark:border-white/8 dark:bg-white/[0.035]">
            <div className="flex flex-col gap-3 border-b border-black/8 p-5 sm:flex-row sm:items-center dark:border-white/8">
                <span className={`w-fit rounded-md border px-2 py-1 font-mono text-[10px] font-bold ${methodStyles[endpoint.method]}`}>
                    {endpoint.method}
                </span>
                <code className="overflow-x-auto font-mono text-sm font-semibold text-[#172012] dark:text-white">/api/v1{endpoint.path}</code>
            </div>
            <div className="space-y-5 p-5">
                <p className="text-sm leading-6 text-black/55 dark:text-white/50">{endpoint.description}</p>
                {endpoint.body && (
                    <div>
                        <p className="mb-2 text-[10px] font-semibold tracking-[0.15em] text-black/35 uppercase dark:text-white/35">Request body</p>
                        <CodeBlock code={endpoint.body} />
                    </div>
                )}
                {endpoint.response && (
                    <div>
                        <p className="mb-2 text-[10px] font-semibold tracking-[0.15em] text-black/35 uppercase dark:text-white/35">
                            Example response
                        </p>
                        <CodeBlock code={endpoint.response} />
                    </div>
                )}
            </div>
        </article>
    );
}

export default function ApiDocs() {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <>
            <Head title="API Documentation">
                <meta name="description" content="Bracket tournament API reference and examples." />
            </Head>
            <div className="min-h-screen bg-[#f4f6ed] text-[#172012] selection:bg-lime-300 dark:bg-[#0b0e09] dark:text-white">
                <header className="sticky top-0 z-30 border-b border-black/8 bg-[#f4f6ed]/90 backdrop-blur-xl dark:border-white/8 dark:bg-[#0b0e09]/90">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10">
                        <Link href={route('home')} className="flex items-center gap-3">
                            <span className="grid size-9 place-items-center rounded-xl bg-lime-400 text-[#10140c]">
                                <Trophy className="size-[18px]" />
                            </span>
                            <span className="font-bold tracking-[-0.03em]">Bracket</span>
                            <span className="hidden text-black/20 sm:inline dark:text-white/20">/</span>
                            <span className="hidden text-sm text-black/50 sm:inline dark:text-white/50">API Docs</span>
                        </Link>
                        <div className="flex items-center gap-2">
                            <Link
                                href={route('home')}
                                className="hidden items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium text-black/50 hover:text-black sm:flex dark:text-white/50 dark:hover:text-white"
                            >
                                <ArrowLeft className="size-4" /> Back to app
                            </Link>
                            <button type="button" onClick={() => setMenuOpen(!menuOpen)} className="rounded-lg p-2 md:hidden">
                                {menuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                            </button>
                        </div>
                    </div>
                </header>

                <div className="mx-auto grid max-w-7xl md:grid-cols-[220px_1fr] lg:grid-cols-[250px_1fr]">
                    <aside
                        className={`${menuOpen ? 'block' : 'hidden'} fixed inset-x-0 top-16 z-20 border-b border-black/8 bg-[#f4f6ed] p-5 md:sticky md:top-16 md:block md:h-[calc(100vh-4rem)] md:border-r md:border-b-0 md:px-8 md:py-10 dark:border-white/8 dark:bg-[#0b0e09]`}
                    >
                        <p className="mb-4 text-[10px] font-bold tracking-[0.16em] text-black/30 uppercase dark:text-white/30">On this page</p>
                        <nav className="space-y-1">
                            {sections.map((section) => (
                                <a
                                    key={section.id}
                                    href={`#${section.id}`}
                                    onClick={() => setMenuOpen(false)}
                                    className="block rounded-lg px-3 py-2 text-sm text-black/55 transition hover:bg-lime-300/20 hover:text-green-900 dark:text-white/50 dark:hover:text-lime-300"
                                >
                                    {section.title}
                                </a>
                            ))}
                            <a
                                href="#responses"
                                onClick={() => setMenuOpen(false)}
                                className="block rounded-lg px-3 py-2 text-sm text-black/55 transition hover:bg-lime-300/20 hover:text-green-900 dark:text-white/50 dark:hover:text-lime-300"
                            >
                                Responses & errors
                            </a>
                        </nav>
                    </aside>

                    <main className="min-w-0 px-5 py-12 sm:px-8 lg:px-12 lg:py-16">
                        <section className="relative overflow-hidden rounded-3xl bg-[#172012] p-7 text-white shadow-xl shadow-black/10 sm:p-10">
                            <div className="absolute -top-24 right-0 size-72 rounded-full bg-lime-400/15 blur-3xl" />
                            <div className="relative max-w-2xl">
                                <div className="mb-5 grid size-11 place-items-center rounded-2xl bg-lime-400/15 text-lime-300">
                                    <Braces className="size-5" />
                                </div>
                                <p className="text-xs font-semibold tracking-[0.17em] text-lime-300 uppercase">REST API · Version 1</p>
                                <h1 className="mt-3 text-4xl font-bold tracking-[-0.05em] sm:text-5xl">Build on Bracket.</h1>
                                <p className="mt-4 max-w-xl text-sm leading-7 text-white/55 sm:text-base">
                                    Create competitions, manage participants, generate brackets, and report results with a predictable JSON API.
                                </p>
                            </div>
                        </section>

                        <section className="mt-10 grid gap-4 sm:grid-cols-2">
                            <div className="rounded-2xl border border-black/8 bg-white/70 p-5 dark:border-white/8 dark:bg-white/[0.035]">
                                <p className="text-xs font-semibold text-black/35 dark:text-white/35">BASE URL</p>
                                <code className="mt-2 block font-mono text-sm font-semibold">/api/v1</code>
                            </div>
                            <div className="rounded-2xl border border-black/8 bg-white/70 p-5 dark:border-white/8 dark:bg-white/[0.035]">
                                <p className="text-xs font-semibold text-black/35 dark:text-white/35">CONTENT TYPE</p>
                                <code className="mt-2 block font-mono text-sm font-semibold">application/json</code>
                            </div>
                        </section>

                        <div className="mt-16 space-y-20">
                            {sections.map((section) => (
                                <section key={section.id} id={section.id} className="scroll-mt-24">
                                    <p className="text-xs font-semibold tracking-[0.16em] text-lime-700 uppercase dark:text-lime-400">
                                        API reference
                                    </p>
                                    <h2 className="mt-2 text-3xl font-bold tracking-[-0.04em]">{section.title}</h2>
                                    <p className="mt-2 max-w-2xl text-sm leading-6 text-black/50 dark:text-white/45">{section.description}</p>
                                    <div className="mt-7 space-y-4">
                                        {section.endpoints.map((endpoint) => (
                                            <EndpointCard key={`${endpoint.method}-${endpoint.path}`} endpoint={endpoint} />
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>

                        <section id="responses" className="mt-20 scroll-mt-24">
                            <p className="text-xs font-semibold tracking-[0.16em] text-lime-700 uppercase dark:text-lime-400">Conventions</p>
                            <h2 className="mt-2 text-3xl font-bold tracking-[-0.04em]">Responses & errors</h2>
                            <p className="mt-3 text-sm leading-6 text-black/50 dark:text-white/45">
                                Successful responses use a consistent envelope. Collection responses also include a{' '}
                                <code className="rounded bg-black/5 px-1.5 py-0.5 dark:bg-white/10">meta.count</code> value.
                            </p>
                            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                                <CodeBlock
                                    code={`{
  "message": "Tournaments retrieved successfully.",
  "data": [...],
  "meta": { "count": 4 }
}`}
                                />
                                <CodeBlock
                                    code={`{
  "message": "The given data was invalid.",
  "errors": {
    "mode_type": ["The selected mode type is invalid."]
  }
}`}
                                />
                            </div>
                            <div className="mt-6 flex flex-wrap gap-2">
                                {['200 OK', '201 Created', '204 No Content', '404 Not Found', '422 Validation Error'].map((status) => (
                                    <span
                                        key={status}
                                        className="rounded-full border border-black/8 bg-white/60 px-3 py-1.5 font-mono text-xs text-black/55 dark:border-white/8 dark:bg-white/5 dark:text-white/50"
                                    >
                                        {status}
                                    </span>
                                ))}
                            </div>
                        </section>

                        <footer className="mt-20 flex items-center justify-between border-t border-black/8 py-8 text-xs text-black/35 dark:border-white/8 dark:text-white/30">
                            <span>Bracket API v1</span>
                            <a
                                href="/api/v1/tournaments"
                                target="_blank"
                                className="flex items-center gap-1.5 transition hover:text-black dark:hover:text-white"
                            >
                                Open API endpoint <ExternalLink className="size-3" />
                            </a>
                        </footer>
                    </main>
                </div>
            </div>
        </>
    );
}

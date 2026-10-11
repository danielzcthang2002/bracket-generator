import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Check, ChevronRight, Trophy, Users, Zap } from 'lucide-react';

const formats = ['Single elimination', 'Double elimination', 'Round robin', 'Swiss', 'Free for all'];
const firstRound = [
    ['Northstar', '8', 'Wildcats', '3'],
    ['Velocity', '5', 'Orbit', '7'],
    ['Phoenix', '9', 'Rangers', '6'],
    ['Titans', '4', 'Comets', '2'],
];

function BrandMark() {
    return (
        <div className="grid size-9 place-items-center rounded-xl bg-lime-400 text-[#10140c] shadow-[0_0_24px_rgba(163,230,53,0.22)]">
            <Trophy className="size-[18px]" strokeWidth={2.4} />
        </div>
    );
}

function Match({ teams, compact = false }: { teams: string[]; compact?: boolean }) {
    return (
        <div
            className={`relative overflow-hidden rounded-lg border border-white/10 bg-[#1b2119]/90 shadow-[0_14px_40px_rgba(0,0,0,0.25)] ${compact ? 'w-[132px]' : 'w-[154px]'}`}
        >
            <div className="flex items-center justify-between border-b border-white/8 px-3 py-2">
                <span className="truncate text-[10px] font-medium text-white/90">{teams[0]}</span>
                <span className="ml-2 text-[10px] font-bold text-lime-300">{teams[1]}</span>
            </div>
            <div className="flex items-center justify-between px-3 py-2">
                <span className="truncate text-[10px] text-white/50">{teams[2]}</span>
                <span className="ml-2 text-[10px] font-semibold text-white/40">{teams[3]}</span>
            </div>
        </div>
    );
}

function BracketPreview() {
    return (
        <div className="relative mx-auto h-[420px] w-full max-w-[610px] overflow-hidden rounded-[1.75rem] border border-white/10 bg-[#11150f] p-5 shadow-[0_40px_100px_rgba(0,0,0,0.45)] sm:p-7">
            <div className="absolute -top-24 right-0 h-72 w-72 rounded-full bg-lime-400/10 blur-3xl" />
            <div className="relative z-10 mb-7 flex items-start justify-between">
                <div>
                    <p className="text-[9px] font-semibold tracking-[0.22em] text-lime-300 uppercase">Live bracket</p>
                    <h2 className="mt-1 text-base font-semibold text-white">Autumn Open 2026</h2>
                </div>
                <div className="flex items-center gap-1.5 rounded-full border border-lime-300/20 bg-lime-300/10 px-2.5 py-1 text-[9px] font-semibold text-lime-300">
                    <span className="size-1.5 animate-pulse rounded-full bg-lime-300" /> Live
                </div>
            </div>
            <div className="relative z-10 grid grid-cols-[1fr_1fr] gap-5 sm:grid-cols-[1fr_1fr_0.8fr]">
                <div className="space-y-3">
                    <p className="mb-3 text-[8px] font-semibold tracking-[0.18em] text-white/30 uppercase">Quarter final</p>
                    {firstRound.map((teams) => (
                        <Match key={teams[0]} teams={teams} compact />
                    ))}
                </div>
                <div className="space-y-[70px] pt-[62px]">
                    <Match teams={['Northstar', '6', 'Orbit', '4']} compact />
                    <Match teams={['Phoenix', '7', 'Titans', '5']} compact />
                </div>
                <div className="hidden pt-[142px] sm:block">
                    <Match teams={['Northstar', '8', 'Phoenix', '7']} compact />
                    <div className="mt-4 flex items-center gap-2 text-[9px] font-medium text-lime-300">
                        <Trophy className="size-3" /> Champion
                    </div>
                </div>
            </div>
            <div className="absolute right-5 bottom-5 flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-[9px] text-white/40 backdrop-blur sm:hidden">
                <Trophy className="size-3 text-lime-300" /> Northstar leads
            </div>
        </div>
    );
}

export default function Welcome() {
    const { auth } = usePage<SharedData>().props;
    const primaryHref = auth.user ? route('tournament.create') : route('register');
    const primaryLabel = auth.user ? 'Create tournament' : 'Start for free';

    return (
        <>
            <Head title="Tournament brackets, made simple">
                <meta name="description" content="Create tournaments, manage players, score matches, and keep every bracket moving." />
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
            </Head>
            <div className="min-h-screen overflow-hidden bg-[#f4f6ed] font-sans text-[#172012] selection:bg-lime-300 dark:bg-[#0b0e09] dark:text-white">
                <div className="absolute inset-x-0 top-0 h-[680px] bg-[radial-gradient(circle_at_70%_20%,rgba(163,230,53,0.14),transparent_34%)] dark:bg-[radial-gradient(circle_at_70%_20%,rgba(163,230,53,0.10),transparent_34%)]" />
                <header className="relative z-20 mx-auto flex max-w-7xl items-center justify-between px-5 py-6 sm:px-8 lg:px-10">
                    <Link href={route('home')} className="flex items-center gap-3" aria-label="Bracket home">
                        <BrandMark />
                        <span className="text-[17px] font-bold tracking-[-0.03em]">Bracket</span>
                    </Link>
                    <nav className="flex items-center gap-2 sm:gap-3">
                        <Link
                            href={route('api.docs')}
                            className="hidden px-3 py-2 text-sm font-medium text-black/60 transition hover:text-black sm:inline-flex dark:text-white/60 dark:hover:text-white"
                        >
                            API docs
                        </Link>
                        {auth.user ? (
                            <Link
                                href={route('dashboard')}
                                className="rounded-full border border-black/10 bg-white/50 px-4 py-2 text-sm font-medium transition hover:bg-white dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={route('login')}
                                    className="px-3 py-2 text-sm font-medium text-black/60 transition hover:text-black dark:text-white/60 dark:hover:text-white"
                                >
                                    Log in
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-full bg-[#172012] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2a3522] dark:bg-lime-400 dark:text-[#11150e] dark:hover:bg-lime-300"
                                >
                                    Sign up
                                </Link>
                            </>
                        )}
                    </nav>
                </header>
                <main className="relative z-10">
                    <section className="mx-auto grid max-w-7xl items-center gap-14 px-5 pt-16 pb-20 sm:px-8 sm:pt-24 lg:grid-cols-[0.92fr_1.08fr] lg:px-10 lg:pt-28 lg:pb-28">
                        <div className="max-w-xl">
                            <div className="mb-7 inline-flex items-center gap-2 rounded-full border border-black/8 bg-white/60 px-3 py-1.5 text-xs font-semibold text-black/60 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-white/60">
                                <Zap className="size-3.5 fill-lime-400 text-lime-500" /> Built for every kind of competition
                            </div>
                            <h1 className="text-5xl leading-[0.98] font-bold tracking-[-0.06em] sm:text-6xl lg:text-7xl">
                                Run the game.<span className="block text-lime-600 dark:text-lime-400">We’ll run the bracket.</span>
                            </h1>
                            <p className="mt-7 max-w-lg text-base leading-7 text-black/55 sm:text-lg dark:text-white/55">
                                Create tournaments, add your players, record every score, and let the next matchup fall into place automatically.
                            </p>
                            <div className="mt-9 flex flex-col gap-3 sm:flex-row">
                                <Link
                                    href={primaryHref}
                                    className="group inline-flex items-center justify-center gap-2 rounded-full bg-[#172012] px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-black/10 transition hover:-translate-y-0.5 hover:bg-[#2a3522] dark:bg-lime-400 dark:text-[#11150e] dark:hover:bg-lime-300"
                                >
                                    {primaryLabel}
                                    <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                </Link>
                                <a
                                    href="#formats"
                                    className="inline-flex items-center justify-center gap-1 rounded-full px-6 py-3.5 text-sm font-semibold text-black/60 transition hover:text-black dark:text-white/60 dark:hover:text-white"
                                >
                                    Explore formats <ChevronRight className="size-4" />
                                </a>
                            </div>
                            <div className="mt-9 flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-black/45 dark:text-white/40">
                                {['Fast setup', 'Automatic advancement', 'Live scoring'].map((item) => (
                                    <span key={item} className="flex items-center gap-1.5">
                                        <Check className="size-3.5 text-lime-600 dark:text-lime-400" />
                                        {item}
                                    </span>
                                ))}
                            </div>
                        </div>
                        <div className="relative lg:translate-x-5">
                            <div className="absolute -inset-10 rounded-full bg-lime-300/15 blur-3xl" />
                            <BracketPreview />
                        </div>
                    </section>
                    <section id="formats" className="border-y border-black/8 bg-white/55 dark:border-white/8 dark:bg-white/[0.025]">
                        <div className="mx-auto grid max-w-7xl gap-8 px-5 py-12 sm:px-8 lg:grid-cols-[0.7fr_1.3fr] lg:items-center lg:px-10">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-lime-700 uppercase dark:text-lime-400">
                                    One platform, every format
                                </p>
                                <h2 className="mt-2 text-2xl font-bold tracking-[-0.035em]">Your tournament, your rules.</h2>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {formats.map((format) => (
                                    <span
                                        key={format}
                                        className="rounded-full border border-black/8 bg-white px-4 py-2 text-sm font-medium text-black/60 shadow-sm dark:border-white/10 dark:bg-white/5 dark:text-white/60"
                                    >
                                        {format}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </section>
                    <section className="mx-auto grid max-w-7xl gap-5 px-5 py-20 sm:px-8 md:grid-cols-3 lg:px-10 lg:py-28">
                        {[
                            {
                                icon: Users,
                                step: '01',
                                title: 'Bring your roster',
                                copy: 'Add players one by one or in bulk. Set check-in rules and entry limits in a few clicks.',
                            },
                            {
                                icon: Zap,
                                step: '02',
                                title: 'Start the competition',
                                copy: 'Choose a format and generate every matchup with the right seeding and round structure.',
                            },
                            {
                                icon: Trophy,
                                step: '03',
                                title: 'Crown a champion',
                                copy: 'Enter results as games finish. Standings and next-round pairings stay up to date.',
                            },
                        ].map(({ icon: Icon, step, title, copy }) => (
                            <article
                                key={step}
                                className="group rounded-3xl border border-black/8 bg-white/55 p-7 transition hover:-translate-y-1 hover:bg-white hover:shadow-xl hover:shadow-black/5 dark:border-white/8 dark:bg-white/[0.035] dark:hover:bg-white/[0.06]"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="grid size-11 place-items-center rounded-2xl bg-lime-300/30 text-lime-700 dark:text-lime-300">
                                        <Icon className="size-5" />
                                    </div>
                                    <span className="text-xs font-semibold text-black/20 dark:text-white/20">{step}</span>
                                </div>
                                <h3 className="mt-7 text-lg font-bold tracking-[-0.025em]">{title}</h3>
                                <p className="mt-3 text-sm leading-6 text-black/50 dark:text-white/45">{copy}</p>
                            </article>
                        ))}
                    </section>
                </main>
                <footer className="border-t border-black/8 px-5 py-8 dark:border-white/8">
                    <div className="mx-auto flex max-w-7xl flex-col gap-4 text-xs text-black/40 sm:flex-row sm:items-center sm:justify-between dark:text-white/35">
                        <div className="flex items-center gap-2">
                            <BrandMark />
                            <span className="ml-1 font-semibold text-black/60 dark:text-white/60">Bracket</span>
                        </div>
                        <p>Every matchup. Every score. One clear path to the podium.</p>
                    </div>
                </footer>
            </div>
        </>
    );
}

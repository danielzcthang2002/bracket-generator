import { Link } from '@inertiajs/react';
import { Check, Trophy } from 'lucide-react';
import { type PropsWithChildren } from 'react';

interface AuthLayoutProps {
    name?: string;
    title?: string;
    description?: string;
}

export default function AuthSimpleLayout({ children, title, description }: PropsWithChildren<AuthLayoutProps>) {
    return (
        <div className="relative min-h-svh overflow-hidden bg-[#f4f6ed] px-5 py-8 text-[#172012] selection:bg-lime-300 sm:px-8 lg:grid lg:place-items-center lg:py-10 dark:bg-[#0b0e09] dark:text-white">
            <div className="absolute -top-40 -right-32 h-[520px] w-[520px] rounded-full bg-lime-300/20 blur-3xl dark:bg-lime-400/10" />
            <div className="relative mx-auto grid w-full max-w-5xl overflow-hidden rounded-[2rem] border border-black/8 bg-white/70 shadow-[0_30px_100px_rgba(23,32,18,0.12)] backdrop-blur md:grid-cols-[0.9fr_1.1fr] dark:border-white/10 dark:bg-[#121610]/90 dark:shadow-black/40">
                <aside className="relative hidden overflow-hidden bg-[#172012] p-10 text-white md:flex md:flex-col lg:p-12">
                    <div className="absolute -right-24 -bottom-24 size-72 rounded-full bg-lime-400/15 blur-3xl" />
                    <Link href={route('home')} className="relative z-10 flex items-center gap-3 font-bold tracking-[-0.03em]">
                        <span className="grid size-10 place-items-center rounded-xl bg-lime-400 text-[#10140c]">
                            <Trophy className="size-5" />
                        </span>
                        <span className="text-lg">Bracket</span>
                    </Link>
                    <div className="relative z-10 my-auto py-16">
                        <p className="text-xs font-semibold tracking-[0.18em] text-lime-300 uppercase">Competition, simplified</p>
                        <h2 className="mt-4 text-4xl leading-tight font-bold tracking-[-0.05em]">From first matchup to final podium.</h2>
                        <p className="mt-5 max-w-sm text-sm leading-6 text-white/55">
                            Build the bracket, manage every score, and keep your tournament moving without the spreadsheet chaos.
                        </p>
                        <div className="mt-8 space-y-3 text-sm text-white/70">
                            {['Five flexible tournament formats', 'Automatic match advancement', 'Live progress at a glance'].map((item) => (
                                <div key={item} className="flex items-center gap-3">
                                    <span className="grid size-5 place-items-center rounded-full bg-lime-400/15">
                                        <Check className="size-3 text-lime-300" />
                                    </span>
                                    {item}
                                </div>
                            ))}
                        </div>
                    </div>
                    <p className="relative z-10 text-xs text-white/30">Run the game. We’ll run the bracket.</p>
                </aside>
                <main className="flex min-h-[640px] flex-col justify-center p-6 sm:p-10 lg:p-14">
                    <Link href={route('home')} className="mb-10 flex items-center gap-2.5 self-start font-bold tracking-[-0.03em] md:hidden">
                        <span className="grid size-9 place-items-center rounded-xl bg-lime-400 text-[#10140c]">
                            <Trophy className="size-[18px]" />
                        </span>
                        Bracket
                    </Link>
                    <div className="mx-auto w-full max-w-sm">
                        <div className="mb-8">
                            <p className="mb-2 text-xs font-semibold tracking-[0.16em] text-lime-700 uppercase dark:text-lime-400">
                                Welcome to Bracket
                            </p>
                            <h1 className="text-3xl font-bold tracking-[-0.045em]">{title}</h1>
                            <p className="mt-2 text-sm leading-6 text-black/45 dark:text-white/45">{description}</p>
                        </div>
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}

import { Head, Link } from '@inertiajs/react';
import { Menu, Trophy, X } from 'lucide-react';
import { useState } from 'react';
import document from '../../../docs/client-features.md?raw';

const blocks = document.trim().split(/\r?\n\s*\r?\n/);
const sectionId = (title: string) =>
    title
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
const sections = blocks.filter((block) => block.startsWith('## ')).map((block) => block.slice(3));

function InlineText({ text }: { text: string }) {
    return text.split(/(\*\*[^*]+\*\*)/g).map((part, index) =>
        part.startsWith('**') ? (
            <strong key={index} className="font-semibold text-[#172012] dark:text-white">
                {part.slice(2, -2)}
            </strong>
        ) : (
            part
        ),
    );
}

// Render the headings, tables, lists, and emphasis used by the local feature document.
// React renders all document text directly, without interpreting raw HTML.
function DocumentBlock({ block }: { block: string }) {
    if (block.startsWith('# ')) return null;
    if (block.startsWith('## ')) {
        const title = block.slice(3);
        return (
            <h2 id={sectionId(title)} className="scroll-mt-24 pt-8 text-2xl font-bold tracking-tight sm:text-3xl">
                {title}
            </h2>
        );
    }
    if (block.startsWith('### ')) return <h3 className="pt-3 text-lg font-semibold">{block.slice(4)}</h3>;
    if (block.startsWith('|')) {
        const rows = block.split(/\r?\n/).map((row) =>
            row
                .split('|')
                .slice(1, -1)
                .map((cell) => cell.trim()),
        );
        return (
            <div className="overflow-x-auto rounded-2xl border border-black/8 bg-white/70 dark:border-white/10 dark:bg-white/[0.035]">
                <table className="w-full text-left text-sm">
                    <thead className="bg-black/[0.03] dark:bg-white/5">
                        <tr>
                            {rows[0].map((cell) => (
                                <th key={cell} scope="col" className="px-5 py-4 font-semibold">
                                    {cell}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.slice(2).map((row, index) => (
                            <tr key={index} className="border-t border-black/8 align-top dark:border-white/10">
                                {row.map((cell, column) =>
                                    column === 0 ? (
                                        <th key={column} scope="row" className="min-w-48 px-5 py-4 font-medium">
                                            <InlineText text={cell} />
                                        </th>
                                    ) : (
                                        <td key={column} className="min-w-64 px-5 py-4 leading-7 text-black/60 dark:text-white/60">
                                            <InlineText text={cell} />
                                        </td>
                                    ),
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        );
    }
    if (/^(- |\d+\. )/.test(block)) {
        const items = block.split(/\r?\n/).map((line, index) => (
            <li key={index} className="pl-1">
                <InlineText text={line.replace(/^(- |\d+\. )/, '')} />
            </li>
        ));
        const className = 'space-y-3 pl-6 text-sm leading-7 text-black/60 dark:text-white/60';
        return block.startsWith('- ') ? (
            <ul className={`list-disc ${className}`}>{items}</ul>
        ) : (
            <ol className={`list-decimal ${className}`}>{items}</ol>
        );
    }
    return (
        <p className="text-sm leading-7 text-black/60 sm:text-base dark:text-white/60">
            <InlineText text={block.replace(/\r?\n/g, ' ')} />
        </p>
    );
}

export default function Features() {
    const [menuOpen, setMenuOpen] = useState(false);

    return (
        <>
            <Head title="Tournament Features">
                <meta
                    name="description"
                    content="Explore bracket previews, seed swapping, result corrections, tournament rollbacks, and five competition formats."
                />
            </Head>
            <div className="min-h-screen bg-[#f4f6ed] text-[#172012] selection:bg-lime-300 dark:bg-[#0b0e09] dark:text-white">
                <header className="sticky top-0 z-30 border-b border-black/8 bg-[#f4f6ed]/90 backdrop-blur-xl dark:border-white/8 dark:bg-[#0b0e09]/90">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-3 px-5 sm:px-8">
                        <Link href={route('home')} className="flex items-center gap-3" aria-label="Bracket home">
                            <span className="grid size-9 place-items-center rounded-xl bg-lime-400 text-[#10140c]">
                                <Trophy className="size-[18px]" />
                            </span>
                            <span className="font-bold tracking-tight">Bracket</span>
                        </Link>
                        <nav aria-label="Page navigation" className="flex items-center gap-3 text-sm font-medium">
                            <Link href={route('api.docs')} className="hover:underline">
                                API docs
                            </Link>
                            <Link href={route('home')} className="hidden rounded-full border border-black/10 px-4 py-2 sm:block dark:border-white/15">
                                Back to app
                            </Link>
                            <button
                                type="button"
                                aria-label={menuOpen ? 'Close contents' : 'Open contents'}
                                aria-expanded={menuOpen}
                                aria-controls="feature-contents"
                                onClick={() => setMenuOpen(!menuOpen)}
                                className="rounded-lg p-2 md:hidden"
                            >
                                {menuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
                            </button>
                        </nav>
                    </div>
                </header>
                <div className="mx-auto grid max-w-7xl md:grid-cols-[220px_1fr] lg:grid-cols-[250px_1fr]">
                    <aside
                        id="feature-contents"
                        className={`${menuOpen ? 'block' : 'hidden'} fixed inset-x-0 top-16 z-20 max-h-[calc(100vh-4rem)] overflow-y-auto border-b border-black/8 bg-[#f4f6ed] p-5 md:sticky md:block md:h-[calc(100vh-4rem)] md:border-r md:border-b-0 md:px-6 md:py-10 dark:border-white/8 dark:bg-[#0b0e09]`}
                    >
                        <p className="mb-4 text-xs font-semibold tracking-widest text-black/40 uppercase dark:text-white/40">On this page</p>
                        <nav aria-label="Feature sections" className="space-y-1">
                            {sections.map((title) => (
                                <a
                                    key={title}
                                    href={`#${sectionId(title)}`}
                                    onClick={() => setMenuOpen(false)}
                                    className="block rounded-lg px-3 py-2 text-sm text-black/60 hover:bg-lime-300/20 hover:text-green-900 dark:text-white/60 dark:hover:text-lime-300"
                                >
                                    {title}
                                </a>
                            ))}
                        </nav>
                    </aside>
                    <main className="min-w-0 px-5 py-12 sm:px-8 lg:px-12 lg:py-16">
                        <section className="relative overflow-hidden rounded-3xl bg-[#172012] p-7 text-white shadow-xl shadow-black/10 sm:p-10">
                            <div className="absolute -top-24 right-0 size-72 rounded-full bg-lime-400/15 blur-3xl" />
                            <div className="relative max-w-2xl">
                                <p className="text-xs font-semibold tracking-widest text-lime-300 uppercase">Features for tournament organizers</p>
                                <h1 className="mt-3 text-4xl font-bold tracking-[-0.05em] sm:text-5xl">Prepare. Play. Adapt.</h1>
                                <p className="mt-4 text-base leading-7 text-white/65">
                                    Preview the draw, adjust your seeds, and keep your tournament moving when results change.
                                </p>
                                <Link
                                    href={route('tournament.index')}
                                    className="mt-6 inline-flex rounded-full bg-lime-400 px-5 py-3 text-sm font-semibold text-[#10140c] hover:bg-lime-300"
                                >
                                    Manage tournaments
                                </Link>
                            </div>
                        </section>
                        <article aria-label="Tournament feature guide" className="mt-10 space-y-6">
                            {blocks.map((block, index) => (
                                <DocumentBlock key={index} block={block} />
                            ))}
                        </article>
                        <footer className="mt-12 border-t border-black/10 pt-6 text-sm dark:border-white/10">
                            Building an integration?{' '}
                            <Link href={route('api.docs')} className="font-semibold text-lime-700 hover:underline dark:text-lime-300">
                                Explore the API documentation
                            </Link>
                            .
                        </footer>
                    </main>
                </div>
            </div>
        </>
    );
}

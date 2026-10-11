import { Trophy } from 'lucide-react';

export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-lime-400 text-[#10140c] shadow-sm">
                <Trophy className="size-4" strokeWidth={2.4} />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-bold tracking-[-0.02em]">Bracket</span>
            </div>
        </>
    );
}

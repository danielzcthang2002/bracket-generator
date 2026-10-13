import { AlertCircle, X } from 'lucide-react';
import { useEffect } from 'react';

interface ErrorToastProps {
    message: string | null;
    onDismiss: () => void;
}

export function ErrorToast({ message, onDismiss }: ErrorToastProps) {
    useEffect(() => {
        if (!message) return;

        const timeout = window.setTimeout(onDismiss, 5000);
        return () => window.clearTimeout(timeout);
    }, [message, onDismiss]);

    if (!message) return null;

    return (
        <div className="fixed top-5 right-5 z-[100] flex w-[min(24rem,calc(100vw-2rem))] items-start gap-3 rounded-xl border border-red-500/30 bg-red-950 px-4 py-3 text-sm text-red-50 shadow-2xl" role="alert" aria-live="assertive">
            <AlertCircle className="mt-0.5 size-5 shrink-0 text-red-300" />
            <p className="flex-1 leading-5">{message}</p>
            <button type="button" onClick={onDismiss} className="rounded-md p-0.5 text-red-200 hover:bg-white/10 hover:text-white" aria-label="Dismiss error">
                <X className="size-4" />
            </button>
        </div>
    );
}

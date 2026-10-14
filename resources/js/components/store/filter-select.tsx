import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const ANY = '__any';

/**
 * A labelled dropdown whose empty choice means "don't filter on this".
 */
export function FilterSelect({
    id,
    label,
    value,
    anyLabel,
    options,
    onChange,
}: {
    id: string;
    label: string;
    value: string | number | null;
    anyLabel?: string;
    options: { value: string | number; label: string }[];
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-xs text-muted-foreground">
                {label}
            </Label>
            <Select
                value={value === null ? ANY : String(value)}
                onValueChange={(next) => onChange(next === ANY ? null : next)}
            >
                <SelectTrigger id={id} className="w-full min-w-36">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {anyLabel && (
                        <SelectItem value={ANY}>{anyLabel}</SelectItem>
                    )}
                    {options.map((option) => (
                        <SelectItem
                            key={option.value}
                            value={String(option.value)}
                        >
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Copy, Option } from '@/types';

/**
 * Fields shared by the add-copy and edit-copy forms.
 */
export function CopyFormFields({
    formats,
    copy,
    errors,
    idPrefix,
}: {
    formats: Option[];
    copy?: Copy;
    errors: Record<string, string | undefined>;
    idPrefix: string;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}-format`}>Format</Label>
                <Select name="format" defaultValue={copy?.format ?? 'vhs'}>
                    <SelectTrigger id={`${idPrefix}-format`}>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {formats.map((format) => (
                            <SelectItem key={format.value} value={format.value}>
                                {format.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.format} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}-acquired`}>Brought home on</Label>
                <Input
                    id={`${idPrefix}-acquired`}
                    type="date"
                    name="acquired_on"
                    defaultValue={copy?.acquired_on ?? ''}
                />
                <InputError message={errors.acquired_on} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}-edition`}>Edition or label</Label>
                <Input
                    id={`${idPrefix}-edition`}
                    name="edition"
                    placeholder="Media Home Entertainment clamshell"
                    defaultValue={copy?.edition ?? ''}
                />
                <InputError message={errors.edition} />
            </div>
            <div className="grid gap-1.5">
                <Label htmlFor={`${idPrefix}-condition`}>Condition</Label>
                <Input
                    id={`${idPrefix}-condition`}
                    name="condition"
                    placeholder="Ex-rental, sticker on spine"
                    defaultValue={copy?.condition ?? ''}
                />
                <InputError message={errors.condition} />
            </div>
            <div className="grid gap-1.5 sm:col-span-2">
                <Label htmlFor={`${idPrefix}-notes`}>Notes</Label>
                <textarea
                    id={`${idPrefix}-notes`}
                    name="notes"
                    rows={2}
                    defaultValue={copy?.notes ?? ''}
                    className="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                />
                <InputError message={errors.notes} />
            </div>
        </div>
    );
}

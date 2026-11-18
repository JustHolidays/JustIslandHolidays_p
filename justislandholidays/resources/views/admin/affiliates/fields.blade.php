<x-field name="name" label="Name (person or company)" :value="$affiliate->name" required />
<x-field name="email" label="Email" type="email" :value="$affiliate->email" required />
<x-field name="phone" label="Phone (optional)" :value="$affiliate->phone" />
<div class="mb-4">
    <label for="affiliate_data" class="mb-1 block font-medium">Private notes</label>
    <p id="affiliate_data-hint" class="mb-1 text-sm text-slate-600">Programme, partner ids, commission terms, company website and contact details. Only super admins can see this; it is never shown publicly.</p>
    <textarea id="affiliate_data" name="affiliate_data" rows="8" aria-describedby="affiliate_data-hint" class="block w-full rounded border border-slate-400 px-3 py-2 font-mono text-sm">{{ old('affiliate_data', $affiliate->affiliate_data) }}</textarea>
</div>

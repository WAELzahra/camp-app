<hr class="my-4 border-t" />
<h3 class="text-md font-semibold text-gray-700">Informations Guide</h3>

<div>
    <x-input-label for="langue" :value="__('Langue parlée')" />
    <x-text-input id="langue" name="langue" type="text" class="mt-1 block w-full"
        :value="old('langue', $profile?->langue)" />
    <x-input-error class="mt-2" :messages="$errors->get('langue')" />
</div>

<div>
    <x-input-label for="experience" :value="__('Expérience (années)')" />
    <x-text-input id="experience" name="experience" type="number" class="mt-1 block w-full"
        :value="old('experience', $profile?->experience)" />
    <x-input-error class="mt-2" :messages="$errors->get('experience')" />
</div>

<div>
    <x-input-label for="tarif" :value="__('Tarif (DT)')" />
    <x-text-input id="tarif" name="tarif" type="number" class="mt-1 block w-full"
        :value="old('tarif', $profile?->tarif)" />
    <x-input-error class="mt-2" :messages="$errors->get('tarif')" />
</div>

<div>
    <x-input-label for="zone_travail" :value="__('Zone de travail')" />
    <x-text-input id="zone_travail" name="zone_travail" type="text" class="mt-1 block w-full"
        :value="old('zone_travail', $profile?->zone_travail)" />
    <x-input-error class="mt-2" :messages="$errors->get('zone_travail')" />
</div>

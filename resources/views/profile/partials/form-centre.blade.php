<hr class="my-4 border-t" />
<h3 class="text-md font-semibold text-gray-700">Informations Centre de Camping</h3>

<div>
    <x-input-label for="capacite" :value="__('Capacité')" />
    <x-text-input id="capacite" name="capacite" type="number" class="mt-1 block w-full"
        :value="old('capacite', $profile?->capacite)" />
    <x-input-error class="mt-2" :messages="$errors->get('capacite')" />
</div>

<div>
    <x-input-label for="service_offrant" :value="__('Services Offerts')" />
    <x-text-input id="service_offrant" name="service_offrant" type="text" class="mt-1 block w-full"
        :value="old('service_offrant', $profile?->service_offrant)" />
    <x-input-error class="mt-2" :messages="$errors->get('service_offrant')" />
</div>

<div>
    <x-input-label for="document_legal" :value="__('Document Légal')" />
    <x-text-input id="document_legal" name="document_legal" type="text" class="mt-1 block w-full"
        :value="old('document_legal', $profile?->document_legal)" />
    <x-input-error class="mt-2" :messages="$errors->get('document_legal')" />
</div>

<div>
    <x-input-label for="disponibilite" :value="__('Disponibilité')" />
    <x-text-input id="disponibilite" name="disponibilite" type="text" class="mt-1 block w-full"
        :value="old('disponibilite', $profile?->disponibilite)" />
    <x-input-error class="mt-2" :messages="$errors->get('disponibilite')" />
</div>

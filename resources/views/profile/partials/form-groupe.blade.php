<hr class="my-4 border-t" />
<h3 class="text-md font-semibold text-gray-700">Informations Groupe</h3>

<div>
    <x-input-label for="nom_groupe" :value="__('Nom du Groupe')" />
    <x-text-input id="nom_groupe" name="nom_groupe" type="text" class="mt-1 block w-full"
        :value="old('nom_groupe', $profile?->nom_groupe)" />
    <x-input-error class="mt-2" :messages="$errors->get('nom_groupe')" />
</div>

<div>
    <x-input-label for="cin_responsable" :value="__('CIN du Responsable')" />
    <x-text-input id="cin_responsable" name="cin_responsable" type="text" class="mt-1 block w-full"
        :value="old('cin_responsable', $profile?->cin_responsable)" />
    <x-input-error class="mt-2" :messages="$errors->get('cin_responsable')" />
</div>

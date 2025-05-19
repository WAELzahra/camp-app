<hr class="my-4 border-t" />
<h3 class="text-md font-semibold text-gray-700">Informations Fournisseur</h3>

<div>
    <x-input-label for="intervale_prix" :value="__('Intervalle de Prix')" />
    <x-text-input id="intervale_prix" name="intervale_prix" type="text" class="mt-1 block w-full"
        :value="old('intervale_prix', $profile?->intervale_prix)" />
    <x-input-error class="mt-2" :messages="$errors->get('intervale_prix')" />
</div>

<div>
    <x-input-label for="product_category" :value="__('Catégorie de Produit')" />
    <x-text-input id="product_category" name="product_category" type="text" class="mt-1 block w-full"
        :value="old('product_category', $profile?->product_category)" />
    <x-input-error class="mt-2" :messages="$errors->get('product_category')" />
</div>

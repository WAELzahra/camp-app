
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#365d26">
    <!-- Inclure Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        .role-button.bg-selected {
            background-color: #365d26;
            /* Couleur de fond pour l'élément sélectionné */
            color: rgb(239, 239, 239);
            /* Texte en blanc pour contraster avec le fond */
        }

        .role-button span {
            display: flex;
            align-items: center;
            /* Aligne les icônes et le texte horizontalement */
        }

        .role-button i {
            margin-right: 8px;
            /* Espace entre l'icône et le nom */
        }
    </style>

    <meta name="msapplication-TileColor" content="#365d26">
    <meta name="theme-color" content="#365d26">

    <meta property="og:image"
        content="https://tailwindcomponents.com/storage/7557/conversions/temp49852-ogimage.jpg?v=2024-02-28 13:24:18" />
    <meta property="og:image:width" content="1280" />
    <meta property="og:image:height" content="640" />
    <meta property="og:image:type" content="image/png" />

    <meta property="og:url" content="https://tailwindcomponents.com/component/sign-up-page-with-side-image/landing" />
    <meta property="og:title" content="Sign Up Page with Side Image by khatabwedaa" />
    <meta property="og:description"
        content="Sign Up Page with Side Image form Meraki UI - Check it here https://merakiui.com/components/sign-in-and-regisration#Sign%20Up%20Page%20with%20Side%20Image" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@TwComponents" />
    <meta name="twitter:title" content="Sign Up Page with Side Image by khatabwedaa" />
    <meta name="twitter:description"
        content="Sign Up Page with Side Image form Meraki UI - Check it here https://merakiui.com/components/sign-in-and-regisration#Sign%20Up%20Page%20with%20Side%20Image" />
    <meta name="twitter:image"
        content="https://tailwindcomponents.com/storage/7557/conversions/temp49852-ogimage.jpg?v=2024-02-28 13:24:18" />

    <link rel="canonical" href="https://tailwindcomponents.com/component/sign-up-page-with-side-image" itemprop="URL">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


    <title>Sign Up Page with Side Image by khatabwedaa. </title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-200">
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif


    <section class="bg-white dark:bg-gray-900">
        <div class="flex justify-center min-h-screen">
            <div class="hidden bg-cover lg:block lg:w-2/5"
                style="background-image: url('https://img.freepik.com/photos-gratuite/bonne-fille-camping-dans-foret-assis-dans-tente_23-2148785796.jpg?t=st=1745189209~exp=1745192809~hmac=8de67e182f9bc0044eaf4e00f8a3e5982521c5d75631033bd4c75b038a49864c&w=740')">
            </div>

            <div class="flex items-center w-full max-w-3xl p-8 mx-auto lg:px-12 lg:w-3/5">
                <div class="w-full">
                    <h1 class="text-2xl font-semibold tracking-wider text-gray-800 capitalize dark:text-white">
                        Get your free account now.
                    </h1>
                    <p class="mt-4 text-gray-500 dark:text-gray-400">
                        Let’s get you all set up so you can verify your personal account and begin setting up your
                        profile.
                    </p>

                    <div x-data="{
                        selected: 'campeur',
                        roles: ['campeur', 'crew', 'guide', 'loueur', 'hote'],
                        icons: {
                            campeur: 'tent',
                            crew: 'users',
                            guide: 'map',
                            loueur: 'key',
                            hote: 'home'
                        }
                    }" x-init="$nextTick(() => lucide.createIcons())" class="mt-6">

                        <h2 class="text-gray-500 dark:text-gray-300 mb-4">Select type of account</h2>

                        <div class="flex flex-wrap justify-center gap-4 mb-6">
                            <template x-for="role in roles" :key="role">
                                <button type="button" @click="selected = role"
                                    :class="selected === role ? 
                                        'bg-[#365d26] text-white border-[#365d26]' : 
                                        'bg-white text-gray-700 border-gray-300 hover:bg-gray-100'"
                                    class="flex items-center gap-1.5 justify-center w-full px-6 py-3 border rounded-lg md:w-auto transition duration-300 ease-in-out transform focus:outline-none">
                                    <i :data-lucide="icons[role]" class="w-5 h-5"
                                        :class="{ 'text-white': selected === role }"></i>
                                    <span x-text="role.charAt(0).toUpperCase() + role.slice(1)"></span>
                                </button>
                            </template>
                        </div>              

                        <!-- Formulaire d'inscription -->
                        <form method="POST" action="{{ route('register') }}"
                            class="grid grid-cols-1 gap-6 mt-8 md:grid-cols-2" x-ref="registerForm">
                            @csrf
                            <input type="hidden" name="role" :value="selected">

                            <div>
                                <label for="name"
                                    class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Name</label>
                                <input id="name" name="name" type="text" required autocomplete="name"
                                    autofocus placeholder="Enter your name"
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out">
                                @error('name')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="email"
                                    class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Email</label>
                                <input id="email" name="email" type="email" required autocomplete="email"
                                    placeholder="Enter your email"
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out">
                                @error('email')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="adresse"
                                    class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Address</label>
                                <input id="adresse" name="adresse" type="text" required autocomplete="address"
                                    placeholder="Enter your address"
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out">
                                @error('adresse')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="phone_number"
                                    class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Phone</label>
                                <input id="phone_number" name="phone_number" type="tel" required
                                    autocomplete="phone" placeholder="Enter your phone number"
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out">
                                @error('phone_number')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="date_naissance" class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Date de naissance</label>
                                <input id="date_naissance" name="date_naissance" type="date" required
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out">
                                @error('date_naissance')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <div>
                                <label for="sexe" class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Sexe</label>
                                <select id="sexe" name="sexe" required
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out">
                                    <option value="" disabled selected>Choisissez votre sexe</option>
                                    <option value="masculin">Masculin</option>
                                    <option value="feminin">Féminin</option>
                                    <option value="autre">Autre</option>
                                </select>
                                @error('sexe')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <label for="bio" class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Bio</label>
                                <textarea id="bio" name="bio" rows="3" placeholder="Parlez-nous un peu de vous"
                                    class="block w-full px-5 py-3 mt-2 text-gray-700 bg-white border rounded-md border-[#365d26] focus:border-[#365d26] focus:ring-[#365d26] dark:bg-gray-900 dark:text-gray-300 transition duration-300 ease-in-out"></textarea>
                                @error('bio')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>



                            <div>
                                <label for="password"
                                    class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Password</label>
                                <div class="relative flex items-center mt-2">
                                    <input id="password" type="password" name="password"
                                        class="block w-full px-5 py-3 mt-2 text-gray-700 placeholder-gray-400 bg-white border border-[#365d26] rounded-md dark:placeholder-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:border-[#365d26] focus:border-[#365d26] dark:focus:border-[#365d26] focus:ring-[#365d26] focus:outline-none focus:ring focus:ring-opacity-40"
                                        required autocomplete="new-password" placeholder="Enter your password">
                                    <button type="button" onclick="togglePasswordVisibility()"
                                        class="absolute right-2 bg-transparent flex items-center justify-center text-gray-700">
                                        <svg id="toggleIcon" class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                                @error('password')
                                    <span class="text-red-500 text-sm" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <script>
                                function togglePasswordVisibility() {
                                    var passwordInput = document.getElementById("password");
                                    var toggleIcon = document.getElementById("toggleIcon");

                                    if (passwordInput.type === "password") {
                                        passwordInput.type = "text";
                                        toggleIcon.innerHTML = `
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                        `;
                                    } else {
                                        passwordInput.type = "password";
                                        toggleIcon.innerHTML = `
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        `;
                                    }
                                }
                            </script>


                            <div>
                                <label for="password_confirmation"
                                    class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Password
                                    Confirmation</label>
                                <div class="relative flex items-center mt-2">
                                    <input id="password_confirmation" type="password" name="password_confirmation"
                                        class="block w-full px-5 py-3 mt-2 text-gray-700 placeholder-gray-400 bg-white border border-[#365d26] rounded-md dark:placeholder-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:border-[#365d26] focus:border-[#365d26] dark:focus:border-[#365d26] focus:ring-[#365d26] focus:outline-none focus:ring focus:ring-opacity-40"
                                        required autocomplete="new-password" placeholder="Confirm your password">
                                    <button onclick="togglePasswordConfirmationVisibility()" type="button"
                                        class="absolute right-2 bg-transparent flex items-center justify-center text-gray-700">
                                        <svg id="confirmationToggleIcon" class="w-5 h-5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <script>
                                function togglePasswordConfirmationVisibility() {
                                    var confirmationInput = document.getElementById("password_confirmation");
                                    var confirmationToggleIcon = document.getElementById("confirmationToggleIcon");

                                    if (confirmationInput.type === "password") {
                                        confirmationInput.type = "text";
                                        confirmationToggleIcon.innerHTML = `
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                        `;
                                    } else {
                                        confirmationInput.type = "password";
                                        confirmationToggleIcon.innerHTML = `
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        `;
                                    }
                                }
                            </script>

                            <button type="submit"
                                class="flex items-center justify-between w-full px-6 py-3 text-sm tracking-wide text-white capitalize transition-gray duration-300 transform bg-gray-500 rounded-md hover:bg-gray-400 focus:outline-none focus:ring focus:ring-blue-300 focus:ring-opacity-50"
                                style="background-color: #365d26;">
                                <span>Subscribe </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 rtl:-scale-x-100"
                                    viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </form>

                        <p style="margin-left: -75%" class="mt-6 text-sm text-center text-gray-400">I have an account
                            <a href="{{ route('login') }}"
                                class="text-blue-500 focus:outline-none focus:underline hover:underline"
                                style="color: #365d26; border-color: #365d26">
                                Sign in
                            </a>.</p>
                    </div>
                </div>
            </div>

    </section>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <style>
        .role-button.bg-selected {
            background-color: #365d26;
            color: rgb(239, 239, 239);
        }

        .role-button span {
            display: flex;
            align-items: center;
        }

        .role-button i {
            margin-right: 8px;
        }
    </style>

    <script>
        document.querySelectorAll('.role-button').forEach(button => {
            button.addEventListener('click', function() {

                document.querySelectorAll('.role-button').forEach(btn => {
                    btn.classList.remove('bg-selected');
                });


                this.classList.add('bg-selected');


                const selectedRole = this.id;
                const alpineRoot = document.querySelector('[x-data]');
                alpineRoot.__x.$data.selected = selectedRole;
            });
        });
    </script>
    <script src="https://unpkg.com/lucide@latest" defer></script>

</body>

</html>

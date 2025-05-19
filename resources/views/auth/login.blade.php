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
    <!-- Ajout de Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <meta name="msapplication-TileColor" content="#365d26">
    <meta name="theme-color" content="#365d26">

    <meta property="og:image"
        content="https://tailwindcomponents.com/storage/6712/conversions/temp30571-ogimage.jpg?v=2024-02-27 21:00:59" />
    <meta property="og:image:width" content="1280" />
    <meta property="og:image:height" content="640" />
    <meta property="og:image:type" content="image/png" />

    <meta property="og:url" content="https://tailwindcomponents.com/component/login-page-with-image/landing" />
    <meta property="og:title" content="Login Page With Image by khatabwedaa" />
    <meta property="og:description" content="Login Page With Image from https://merakiui.com/components" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@TwComponents" />
    <meta name="twitter:title" content="Login Page With Image by khatabwedaa" />
    <meta name="twitter:description" content="Login Page With Image from https://merakiui.com/components" />
    <meta name="twitter:image"
        content="https://tailwindcomponents.com/storage/6712/conversions/temp30571-ogimage.jpg?v=2024-02-27 21:00:59" />

    <link rel="canonical" href="https://tailwindcomponents.com/component/login-page-with-image" itemprop="URL">

    <title>Login Page With Image by khatabwedaa. </title>

    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Custom CSS for Notification -->
    <style>
        .notification {
            font-family: Arial, sans-serif;
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: rgba(212, 237, 218, 0.9);
            /* Légèrement transparent */
            color: #155724;
            /* Couleur de texte verte foncée */
            padding: 15px;
            border-radius: 5px;
            z-index: 9999;
            display: none;
        }
    </style>

</head>

<body class="bg-[#f0f4f8]">

    <!-- Bouton de retour vers la landing page -->
    <style>
        /* Style pour positionner le bouton de retour */
        .back-to-home {
            position: fixed;
            top: 20px;
            /* Distance depuis le haut de la fenêtre */
            left: 20px;
            /* Distance depuis la gauche de la fenêtre */
            background-color: rgba(0, 0, 0, 0.5);
            /* Fond semi-transparent noir */
            color: white;
            padding: 10px;
            border-radius: 50%;
            /* Pour rendre le bouton rond */
            z-index: 9999;
            /* Pour s'assurer que le bouton est au-dessus des autres éléments */
            text-decoration: none;
            /* Supprimer le soulignement du lien */
        }

        .back-to-home i {
            font-size: 20px;
            /* Taille de l'icône */
        }
    </style>
    {{-- <a href="" class="back-to-home">
        <i class="fas fa-home"></i> <!-- Icône de maison pour représenter la landing page -->
    </a> --}}

    @if (Session::has('success'))
        <!-- Custom JS for Notification -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var notification = document.querySelector('.notification');
                notification.textContent = "{{ Session::get('success') }}";
                notification.style.display = 'block';
                notification.classList.add('alert',
                    'alert-success'); // Ajout des classes Bootstrap pour la notification de succès
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 5000); // Disparaître après 5 secondes
            });
        </script>
        <!-- Notification HTML -->
        <div class="notification alert alert-success alert-dismissible fade show" role="alert">
            <strong>Success!</strong>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif


    <div class="bg-white dark:bg-gray-900">
        <div class="flex justify-center h-screen">
            <div class="hidden bg-cover lg:block lg:w-2/3"
                style="background-image: url('https://img.freepik.com/photos-gratuite/beau-tir-tente-orange-montagne-rocheuse-entouree-arbres-au-coucher-du-soleil_181624-3908.jpg?t=st=1745163885~exp=1745167485~hmac=cd5111a5262047b6a4939a82e1e1ef36bcb403bd22959165a8e894e19f797fdb&w=1380')">
                <div class="flex items-center h-full px-20 bg-gray-900 bg-opacity-40">
                </div>
            </div>

            <div class="flex items-center w-full max-w-md px-6 mx-auto lg:w-2/6">
                <div class="flex-1">
                    <div class="text-center">
                        <h2 class="text-4xl font-bold text-center text-gray-700 dark:text-white" style="color:#365d26;">
                            Brand</h2>

                        <p class="mt-3 text-gray-500 dark:text-gray-300">Sign in to access your account</p>
                    </div>

                    <div class="mt-8">
                        @if (Session::has('status'))
                            <!-- Custom JS for Notification -->
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var notification = document.querySelector('.notification');
                                    notification.textContent = "{{ Session::get('status') }}";
                                    notification.style.display = 'block';
                                    setTimeout(function() {
                                        notification.style.display = 'none';
                                    }, 5000); // Disappear after 5 seconds
                                });
                            </script>
                            <!-- Notification HTML -->
                            <div class="notification"
                                style="background-color: #d4edda; color: #155724; border-color: #c3e6cb;">
                                <strong>Succès!</strong> {{ Session::get('status') }}
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        @endif
                    
                        <!-- Formulaire de connexion -->
                        <form method="POST" action="/login">
                            @csrf
                            <div>
                                <label for="email" class="block mb-2 text-sm text-gray-600 dark:text-gray-200">Email Address</label>
                                <input type="email" name="email" id="email" placeholder="example@example.com"
                                    class="block w-full px-4 py-2 mt-2 text-gray-700 placeholder-gray-400 bg-white rounded-md border border-[#155724] dark:placeholder-gray-600 dark:bg-gray-900 dark:text-gray-300 focus:border-[#155724] focus:ring-[#155724] focus:outline-none focus:ring focus:ring-opacity-40 transition duration-300 ease-in-out"
                                    value="{{ old('email') }}" />
                                @if ($errors->has('email'))
                                    <div class="mb-4 text-sm text-red-600">
                                        {{ $errors->first('email') }}
                                    </div>
                                @endif
                            </div>
                    
                            <!-- Password Field -->
                            <div class="mt-6">
                                <div class="flex justify-between mb-2">
                                    <label for="password"
                                        class="text-sm text-gray-600 dark:text-gray-200">Password</label>
                                    @if (Route::has('password.request'))
                                        <a href="{{ route('password.request') }}"
                                            class="text-sm text-gray-400 hover:text-[#155724] hover:underline focus:text-[#155724]">
                                            Forgot password?
                                        </a>
                                    @endif
                                </div>
                                <div class="relative">
                                    <input type="password" name="password" id="password"
                                        placeholder="Your Password"
                                        class="block w-full px-4 py-2 mt-2 text-gray-700 placeholder-gray-400 bg-white border rounded-md border-[#155724] dark:placeholder-gray-600 dark:bg-gray-900 dark:text-gray-300 focus:border-[#155724] focus:ring-[#155724] focus:outline-none focus:ring focus:ring-opacity-40 transition duration-300 ease-in-out" />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer"
                                        onclick="togglePassword()">
                                        <i id="eyeIcon" class="fas fa-eye fa-xs text-gray-600"></i>
                                    </span>
                                </div>
                                @error('password')
                                    <span class="text-red-500 text-xs italic">{{ $message }}</span>
                                @enderror
                            </div>
                            <script>
                                function togglePassword() {
                                    const passwordInput = document.getElementById('password');
                                    const eyeIcon = document.getElementById('eyeIcon');
                                    if (passwordInput.type === 'password') {
                                        passwordInput.type = 'text';
                                        eyeIcon.classList.remove('fa-eye');
                                        eyeIcon.classList.add('fa-eye-slash');
                                    } else {
                                        passwordInput.type = 'password';
                                        eyeIcon.classList.remove('fa-eye-slash');
                                        eyeIcon.classList.add('fa-eye');
                                    }
                                }
                            </script>
                            <div class="checkbox mb-5" style="margin-top: 3%">
                                <label>
                                    <input type="checkbox" name="remember" value="remember-me"
                                        {{ old('remember') ? 'checked' : '' }}>
                                    Stay logged in
                                </label>
                            </div>
                    
                            <div class="mt-3">
                                <button type="submit"
                                    class="w-full px-4 py-2 tracking-wide text-white transition-colors duration-200 transform bg-opacity-100 bg-gray-500 rounded-md hover:bg-opacity-75 hover:bg-gray-400 focus:outline-none focus:bg-opacity-75 focus:ring focus:ring-blue-300 focus:ring-opacity-50"
                                    style="background-color: #365d26">
                                    Sign in
                                </button>
                            </div>
                            <!-- Méthodes de connexion sociales -->
                            <div class="flex flex-col items-center space-y-3 mt-6">

                                <!-- Bouton Facebook -->
                                <a href="{{ url('auth/facebook') }}"
                                   class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-100 transition duration-200">
                                    <i class="fab fa-facebook text-blue-600 mr-2"></i>
                                    <span class="text-sm text-gray-700 font-medium">Se connecter avec Facebook</span>
                                </a>
                            
                                <!-- Bouton Google -->
                                <a href="{{ url('auth/google') }}"
                                   class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-100 transition duration-200">
                                    <i class="fas fa-envelope text-gray-600 mr-2"></i>
                                    <span class="text-sm text-gray-700 font-medium">Se connecter avec Google</span>
                                </a>
                            
                                {{-- <!-- Bouton Email classique -->
                                <a href="{{ route('login') }}"
                                   class="w-full flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm bg-white hover:bg-gray-100 transition duration-200">
                                    <i class="fas fa-envelope text-gray-600 mr-2"></i>
                                    <span class="text-sm text-gray-700 font-medium">Se connecter avec Email</span>
                                </a> --}}
                            </div>
                            

                            <!-- Ligne de séparation -->
                            <div class="flex items-center my-6">
                                <div class="flex-grow h-px bg-gray-300"></div>
                                <span class="mx-4 text-gray-500 text-sm">ou</span>
                                <div class="flex-grow h-px bg-gray-300"></div>
                            </div>
                        </form>
                    
                        <p class="mt-6 text-sm text-center text-gray-400">Don&#x27;t have an account yet? <a href="{{ route('register') }}"
                                class="text-blue-500 focus:outline-none focus:underline hover:underline" style="color:  #365d26;">Create account</a>.</p>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</body>

</html>



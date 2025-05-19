{{-- <x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Reset Password') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout> --}}





<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">


    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#155724">
    <meta name="msapplication-TileColor" content="#155724">
    <meta name="theme-color" content="#155724">

    <meta property="og:image" content="https://tailwindcomponents.com/storage/11919/conversions/temp95596-ogimage.jpg?v=2024-04-17 21:51:03" />
    <meta property="og:image:width" content="1280" />
    <meta property="og:image:height" content="640" />
    <meta property="og:image:type" content="image/png" />

    <meta property="og:url" content="https://tailwindcomponents.com/component/alx-website-clone/landing" />
    <meta property="og:title" content="User Authentication Form by Kaleab S." />
    <meta property="og:description" content="This HTML code snippet is a user authentication form for a web application. It features a clean and modern design with a focus on user experience. The form includes fields for the user to enter their email address, and a button to submit the form. Upon submission, a code is sent to the user’s inbox, eliminating the need for passwords. The form also includes a link for users who do not have an account to create one. This form is designed to be responsive and should look good on all devices. It uses various CSS classes for styling and layout. The code is well-structured and easy to understand, making it a great starting point for any web developer looking to implement a similar feature in their application." />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@TwComponents" />
    <meta name="twitter:title" content="User Authentication Form by Kaleab S." />
    <meta name="twitter:description" content="This HTML code snippet is a user authentication form for a web application. It features a clean and modern design with a focus on user experience. The form includes fields for the user to enter their email address, and a button to submit the form. Upon submission, a code is sent to the user’s inbox, eliminating the need for passwords. The form also includes a link for users who do not have an account to create one. This form is designed to be responsive and should look good on all devices. It uses various CSS classes for styling and layout. The code is well-structured and easy to understand, making it a great starting point for any web developer looking to implement a similar feature in their application." />
    <meta name="twitter:image" content="https://tailwindcomponents.com/storage/11919/conversions/temp95596-ogimage.jpg?v=2024-04-17 21:51:03" />

    <link rel="canonical" href="https://tailwindcomponents.com/component/alx-website-clone" itemprop="URL">
    
    <title>User Authentication Form by Kaleab S.. </title>

            <script src="https://cdn.tailwindcss.com"></script>
    </head>
<body class="bg-gray-200">
    
    <!-- Main container -->
<div class="bg-white">
  <!-- Flex container for centering items -->
  <div class="flex h-screen flex-col items-center justify-center">
    <!-- Container for login form -->
    <div class="max-h-auto mx-auto max-w-xl">
      <!-- Login title and description -->
      <div class="mb-8 space-y-3">
        <p class="text-xl font-semibold" style="color: #155724;">{{ __('Reset Password') }}</p>


        <div class="card-body">
            @if (session('status'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <strong class="font-bold">{{ session('status') }}</strong>
    </div>
@endif


        <p class="text-gray-500">Enter your email, and we'll send a code to your inbox. <br />No need for passwords -- like magic!</p>
      </div>
      <!-- Login form -->
      {{-- <form method="POST" action="{{ route('password.store') }}">
        @csrf
    
        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ request()->route('token') }}">
    
        <!-- Email Address -->
        <div class="mb-4">
            <label for="email" class="block text-sm text-gray-600 dark:text-gray-200">Email</label>
            <input type="email" name="email" id="email" class="w-full px-3 py-2 border rounded-md"
                   value="{{ old('email', request()->email) }}" required autofocus autocomplete="username">
            @error('email')
                <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
            @enderror
        </div>
    
        <!-- Password -->
        <div class="mb-4">
            <label for="password" class="block text-sm text-gray-600 dark:text-gray-200">New Password</label>
            <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-md" required autocomplete="new-password">
            @error('password')
                <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
            @enderror
        </div>
    
        <!-- Confirm Password -->
        <div class="mb-4">
            <label for="password_confirmation" class="block text-sm text-gray-600 dark:text-gray-200">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="w-full px-3 py-2 border rounded-md" required autocomplete="new-password">
            @error('password_confirmation')
                <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
            @enderror
        </div>
    
        <!-- Submit Button -->
        <div class="flex justify-end mt-4">
            
            <button type="submit" class="ring-offset-background focus-visible:ring-ring flex h-10 w-full items-center justify-center
             whitespace-nowrap rounded-md bg-black px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-black/90 focus-visible:outline-none 
             focus-visible:ring-2 focus-visible:ring-offset-2" style="background-color: #155724;">Reset Password</button>

        </div>
    </form> --}}
    
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
    
        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ request()->route('token') }}">
    
        <!-- Email Address -->
        <div class="mb-4">
            <label for="email" class="block text-sm text-gray-600 dark:text-gray-200">Email</label>
            <input type="email" name="email" id="email" class="w-full px-3 py-2 border rounded-md"
                   value="{{ old('email', request()->email) }}" required autofocus autocomplete="username">
            @error('email')
                <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
            @enderror
        </div>
    
        <!-- Password -->
        <div class="mb-4 relative">
            <label for="password" class="block text-sm text-gray-600 dark:text-gray-200">New Password</label>
            <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-md" required autocomplete="new-password">
            <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePassword()">
                <i id="eyeIcon" class="fas fa-eye fa-xs text-gray-600"></i>
            </span>
            @error('password')
                <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
            @enderror
        </div>
    
        <!-- Confirm Password -->
        <div class="mb-4 relative">
            <label for="password_confirmation" class="block text-sm text-gray-600 dark:text-gray-200">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" class="w-full px-3 py-2 border rounded-md" required autocomplete="new-password">
            <span class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer" onclick="togglePasswordConfirmation()">
                <i id="eyeIconConfirmation" class="fas fa-eye fa-xs text-gray-600"></i>
            </span>
            @error('password_confirmation')
                <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
            @enderror
        </div>
    
        <!-- Submit Button -->
        <div class="flex justify-end mt-4">
            <button type="submit" class="ring-offset-background focus-visible:ring-ring flex h-10 w-full items-center justify-center
             whitespace-nowrap rounded-md bg-black px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-black/90 focus-visible:outline-none 
             focus-visible:ring-2 focus-visible:ring-offset-2" style="background-color: #155724;">Reset Password</button>
        </div>
    </form>
    
    <script>
        // Toggle visibility of the password
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
    
        // Toggle visibility of the password confirmation
        function togglePasswordConfirmation() {
            const passwordConfirmationInput = document.getElementById('password_confirmation');
            const eyeIconConfirmation = document.getElementById('eyeIconConfirmation');
            if (passwordConfirmationInput.type === 'password') {
                passwordConfirmationInput.type = 'text';
                eyeIconConfirmation.classList.remove('fa-eye');
                eyeIconConfirmation.classList.add('fa-eye-slash');
            } else {
                passwordConfirmationInput.type = 'password';
                eyeIconConfirmation.classList.remove('fa-eye-slash');
                eyeIconConfirmation.classList.add('fa-eye');
            }
        }
    </script>
    
    <style>
        .relative {
            position: relative;
        }
        .absolute {
            position: absolute;
            top: 68%;
            transform: translateY(-50%);
        }
    </style>
    

    </div>
  </div>
</div>
</body>
</html>





{{-- <!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-200">
    <div class="flex h-screen items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
            <h2 class="text-2xl font-bold mb-6 text-center text-[#60c4ac]">{{ __('Reset Password') }}</h2>

            @if (session('status'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.store') }}">
                @csrf
            
                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ request()->route('token') }}">
            
                <!-- Email Address -->
                <div class="mb-4">
                    <label for="email" class="block text-sm text-gray-600 dark:text-gray-200">Email</label>
                    <input type="email" name="email" id="email" class="w-full px-3 py-2 border rounded-md"
                           value="{{ old('email', request()->email) }}" required autofocus autocomplete="username">
                    @error('email')
                        <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            
                <!-- Password -->
                <div class="mb-4">
                    <label for="password" class="block text-sm text-gray-600 dark:text-gray-200">New Password</label>
                    <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded-md" required autocomplete="new-password">
                    @error('password')
                        <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            
                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="password_confirmation" class="block text-sm text-gray-600 dark:text-gray-200">Confirm Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="w-full px-3 py-2 border rounded-md" required autocomplete="new-password">
                    @error('password_confirmation')
                        <span class="text-red-600 text-sm mt-1 block">{{ $message }}</span>
                    @enderror
                </div>
            
                <!-- Submit Button -->
                <div class="flex justify-end mt-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                        Reset Password
                    </button>
                </div>
            </form>
            
        </div>
    </div>
</body>
</html> --}}

{{-- <x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Password Reset Link') }}
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

    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="mask-icon" href="/safari-pinned-tab.svg" color="#155724;">
    <meta name="msapplication-TileColor" content="#155724;">
    <meta name="theme-color" content="#155724;">

    <meta property="og:image" content="https://tailwindcomponents.com/storage/11919/conversions/temp95596-ogimage.jpg?v=2024-04-17 21:51:03" />
    <meta property="og:image:width" content="1280" />
    <meta property="og:image:height" content="640" />
    <meta property="og:image:type" content="image/png" />

    <meta property="og:url" content="https://tailwindcomponents.com/component/alx-website-clone/landing" />
    <meta property="og:title" content="User Authentication Form by Kaleab S." />
    <meta property="og:description" content="This HTML code snippet is a user authentication form for a web application. It features a clean and modern design with a focus on user experience." />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@TwComponents" />
    <meta name="twitter:title" content="User Authentication Form by Kaleab S." />
    <meta name="twitter:description" content="This HTML code snippet is a user authentication form for a web application." />
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
                    <p class="text-xl font-semibold" style="color: #155724;;">{{ __('Reset Password') }}</p>

                    <!-- Session Status -->
                    @if (session('status'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                        <strong class="font-bold">{{ session('status') }}</strong>
                    </div>
                    @endif

                    <p class="text-gray-500">Enter your email, and we'll send a code to your inbox. <br />No need for passwords -- like magic!</p>
                </div>

                <!-- Login form -->
                <form method="POST" action="{{ route('password.email') }}" class="w-full">
                    @csrf
                    <div class="mb-10 space-y-3">
                        <div class="space-y-1">
                            <div class="space-y-2">
                                <label class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70" for="email">{{ __('Email Address') }}</label>

                                <!-- Email Input Field -->
                                <input id="email" type="email"
                                class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder:text-gray-500
                                       border border-black dark:border-gray-400
                                       focus:border-black focus:ring-0
                                       rounded-md px-4 py-2 w-full text-sm
                                       transition duration-200 ease-in-out shadow-sm"
                                name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                            
                                
                                <!-- Display Errors -->
                                @error('email')
                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="ring-offset-background focus-visible:ring-ring flex h-10 w-full items-center justify-center whitespace-nowrap rounded-md bg-black px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-black/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2" style="background-color: #155724;">{{ __('Send Password Reset Link') }}</button>

                    </div>
                </form>

                <!-- Signup link -->
                <div class="text-center">
                    No account? <a class="text-blue-500" href="{{ route('register') }}" style="color: #155724;">Create one</a>
                </div>
                
            </div>
        </div>
    </div>
</body>
</html>

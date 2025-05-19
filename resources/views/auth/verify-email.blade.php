{{-- <x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <div>
                <x-primary-button>
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout> --}}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style> 
    body{

        background-color: #f8fafc;
    }
</style>
<body class="bg-gray-100">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-8 space-y-6">
            <div class="space-y-2 text-center">
                <h2 class="text-2xl font-bold text-green-700">{{ __('Email Verification') }}</h2>
                <p class="text-sm text-gray-600">
                    {{ __("Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you?") }}
                </p>
                <p class="text-sm text-gray-600">
                    {{ __("If you didn’t receive the email, we will gladly send you another.") }}
                </p>
            </div>

            @if (session('status') == 'verification-link-sent')
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded text-sm text-center">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @endif

            <div class="space-y-4">
                <form method="POST" action="{{ route('verification.send') }}">
                    @csrf
                    <button type="submit"
                        class="w-full py-2 px-4 bg-green-700 text-white rounded-md font-semibold text-sm hover:bg-green-800 transition">
                        {{ __('Resend Verification Email') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full py-2 px-4 bg-gray-100 text-gray-700 rounded-md font-semibold text-sm hover:text-red-600 hover:bg-gray-200 transition">
                        {{ __('Log Out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>


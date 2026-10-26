<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Sign in')])
    </head>
    <body class="min-h-screen">
        <div class="flex min-h-svh items-center justify-center p-6">
            <div class="w-full max-w-sm">
                <img src="/images/formwerks-navy.png" alt="Formwerks" class="mx-auto mb-10 w-56">

                <div class="card border-t-2 border-t-gold p-8 text-center">
                    <h1 class="page-title text-[1.6rem]">{{ __('Management Dashboard') }}</h1>
                    <p class="mt-2 text-sm text-muted">{{ __('Sign in with your Formwerks Microsoft account.') }}</p>

                    @if($errors->any())
                        <div class="error-note mt-6 text-left">{{ $errors->first() }}</div>
                    @endif

                    <a href="{{ route('auth.microsoft.redirect') }}" class="btn mt-8 w-full justify-center py-3">
                        <svg class="size-4" viewBox="0 0 23 23" aria-hidden="true">
                            <path fill="#f35325" d="M1 1h10v10H1z" />
                            <path fill="#81bc06" d="M12 1h10v10H12z" />
                            <path fill="#05a6f0" d="M1 12h10v10H1z" />
                            <path fill="#ffba08" d="M12 12h10v10H12z" />
                        </svg>
                        {{ __('Sign in with Microsoft') }}
                    </a>
                </div>
            </div>
        </div>
    </body>
</html>

{{-- Laravel ships a named view for 401/402/403/404/419/429 (see
     vendor/laravel/framework/.../Exceptions/views), each extending the
     shared errors::minimal layout, but has none for 400 — an abort_if(...,
     400, 'some specific message') anywhere in the app (see
     QuoteRequestController::pdf(), which uses this for three separate
     preconditions: no client selected, no offer selected, and a selected
     offer missing its price) falls through to Symfony's generic "Oops! An
     Error Occurred" page, which never shows the message at all. 403.blade.php
     already establishes the precedent of surfacing $exception->getMessage()
     — this just extends the same treatment to 400, so every abort_if(400, …)
     in the app actually reaches the user instead of a message that only
     ever existed in APP_DEBUG=true's dev-only trace. --}}
@extends('errors::minimal')

@section('title', __('Bad Request'))
@section('code', '400')
@section('message', __($exception->getMessage() ?: 'Bad Request'))

@props([
    'title' => null,
    'description' => null,
    'image' => null,
])

@php
    $pageTitle = $title ?: config('app.name');
    $metaDescription = $description ?: 'Skoolyst Social AI helps teams manage, schedule, and analyze social media posts.';
    $canonical = url()->current();
    $shareImage = $image ?: asset('favicon.ico');
@endphp

<title>{{ $pageTitle }} &mdash; {{ config('app.name') }}</title>
<meta name="description" content="{{ $metaDescription }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:type" content="website">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $shareImage }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ $shareImage }}">

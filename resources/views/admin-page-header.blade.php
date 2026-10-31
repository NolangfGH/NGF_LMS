<header class="app-admin-page-header">
    <div class="app-admin-page-header__inner">
        @include('brand-logo',['logoHref'=>route('teacher.dashboard'),'logoVariant'=>'header'])
        <a href="{{ route('teacher.blog') }}" class="text-sm font-bold text-primary">Blog</a>
        @include('admin-profile-chip')
    </div>
</header>

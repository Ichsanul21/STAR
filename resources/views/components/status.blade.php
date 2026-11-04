@props(['status' => 'todo'])

@php
    $map = [
        'todo' => ['Todo', 'badge-muted'],
        'draft' => ['Draft', 'badge-muted'],
        'active' => ['Aktif', 'badge-success'],
        'closed' => ['Selesai', 'badge-muted'],
        'in_progress' => ['Berjalan', 'badge-brand'],
        'blocked' => ['Blocked', 'badge-danger'],
        'review' => ['Review', 'badge-warning'],
        'done' => ['Done', 'badge-success'],
        'cancelled' => ['Batal', 'badge-muted'],
        'inactive' => ['Nonaktif', 'badge-muted'],
        'submitted' => ['Menunggu', 'badge-warning'],
        'approved' => ['Approved', 'badge-success'],
        'rejected' => ['Ditolak', 'badge-danger'],
        'paid' => ['Paid', 'badge-success'],
        'reimbursed' => ['Reimbursed', 'badge-success'],
        'pending' => ['Pending', 'badge-warning'],
        'published' => ['Published', 'badge-success'],
        'unpaid' => ['Unpaid', 'badge-danger'],
        'partial' => ['Partial', 'badge-warning'],
        'overdue' => ['Overdue', 'badge-danger'],
        'void' => ['Void', 'badge-muted'],
        'low' => ['Low', 'badge-muted'],
        'medium' => ['Medium', 'badge-brand'],
        'high' => ['High', 'badge-warning'],
        'urgent' => ['Urgent', 'badge-danger'],
    ];
    [$label, $class] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'badge-muted'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$class]) }}>{{ $label }}</span>

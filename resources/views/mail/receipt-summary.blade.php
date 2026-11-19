<!DOCTYPE html>
<html lang="id">
<body style="font-family: Helvetica, Arial, sans-serif; color: #1a1a1a; line-height: 1.5">
    <h2 style="font-weight: 600">Penerimaan {{ $receipt->number }}</h2>
    <p style="white-space: pre-line">{{ $summary }}</p>
    <p><a href="{{ $url }}" style="color: #c0261b">Buka penerimaan untuk meninjau dan menyetujui</a></p>
    <p style="color: #727171; font-size: 12px">Pesan otomatis dari aplikasi Nikkou.</p>
</body>
</html>

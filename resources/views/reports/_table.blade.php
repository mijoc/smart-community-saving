<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
  @page  { margin: 14mm 10mm; }
  body   { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#1d1d1d; }
  h1     { font-size: 16px; margin: 0 0 4px; }
  .gen   { font-size: 9px; color: #666; margin-bottom: 10px; }
  .meta  { margin: 0 0 12px; font-size: 10px; }
  .meta div { margin: 1px 0; }
  table  { width: 100%; border-collapse: collapse; }
  thead th {
      background: #1f2a4d; color: #fff; padding: 6px 5px;
      border: 1px solid #1f2a4d; text-align: left; font-size: 10px;
  }
  tbody td {
      border: 1px solid #d6d6d6; padding: 5px; vertical-align: top;
  }
  tbody tr:nth-child(even) td { background: #f6f7fb; }
  .footer {
      position: fixed; bottom: -8mm; left: 0; right: 0;
      text-align: center; font-size: 8px; color:#888;
  }
</style>
</head>
<body>
  <h1>{{ $title }}</h1>
  <div class="gen">Generated: {{ $genAt }}</div>

  @if(!empty($meta))
    <div class="meta">
      @foreach($meta as $k => $v)
        <div><strong>{{ $k }}:</strong> {{ $v }}</div>
      @endforeach
    </div>
  @endif

  <table>
    <thead>
      <tr>
        @foreach($headers as $h)
          <th>{{ $h }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @forelse($rows as $r)
        <tr>
          @foreach($r as $cell)
            <td>{{ is_scalar($cell) || $cell === null ? $cell : (string) $cell }}</td>
          @endforeach
        </tr>
      @empty
        <tr><td colspan="{{ count($headers) }}" style="text-align:center;color:#888;padding:18px;">
          No records.
        </td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="footer">VSLA Manager · {{ $title }}</div>
</body>
</html>

{{-- One file's compress progress, from MediaCompressService::status(). --}}
@switch ($status['state'] ?? '')
    @case('queued')
        <span class="ht-ml-tag ht-ml-tag--info">Queued · {{ strtoupper($status['codec'] ?? '') }}</span>
        @break
    @case('running')
        <span class="ht-ml-tag ht-ml-tag--info">Compressing{{ isset($status['percent']) ? ' '.$status['percent'].'%' : '…' }}</span>
        @break
    @case('done')
        <span class="ht-ml-tag ht-ml-tag--success" title="{{ basename($status['target'] ?? '') }}">
            Compressed · saved {{ \App\Support\Bytes::saving((int) ($status['before'] ?? 0), (int) ($status['after'] ?? 0), 0) }}
        </span>
        @break
    @case('failed')
        <span class="ht-ml-tag ht-ml-tag--danger" title="{{ $status['error'] ?? '' }}">Compress failed</span>
        @break
@endswitch

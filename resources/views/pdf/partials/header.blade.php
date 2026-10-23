{{-- Center identity block shared by invoices and receipts. --}}
<table class="header">
    <tr>
        <td class="center-info">
            <div class="center-name">{{ $center['name'] }}</div>
            @if ($center['branch'])
                <div>{{ $center['branch'] }}</div>
            @endif
            @if ($center['address'])
                <div>{{ $center['address'] }}</div>
            @endif
            @if ($center['phone'])
                <div>{{ $center['phone'] }}</div>
            @endif
        </td>
        @if ($center['logo_path'])
            <td class="logo"><img src="{{ $center['logo_path'] }}" alt=""></td>
        @endif
    </tr>
</table>

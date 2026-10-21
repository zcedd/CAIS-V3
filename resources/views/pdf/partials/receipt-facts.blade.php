<table class="meta">
    @foreach ($rows as $row)
        <tr>
            @foreach ($row as $fact)
                <td @if ($fact['wide']) colspan="2" @endif>
                    <div class="label">{{ $fact['label'] }}</div>
                    <div @class(['mono' => $fact['mono']])>{{ $fact['value'] }}</div>
                </td>
            @endforeach
            @if (count($row) === 1 && ! $row[0]['wide'])
                <td></td>
            @endif
        </tr>
    @endforeach
</table>

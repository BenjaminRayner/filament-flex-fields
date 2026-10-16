<div class="fff-user-column-playground">
    <div class="fff-user-column-playground__table-wrap">
        <table class="fff-user-column-playground__table" aria-label="{{ $title }} examples">
            <thead>
                <tr><th scope="col">Record</th><th scope="col">{{ $title }}</th></tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="fff-user-column-playground__project">{{ $row['label'] }}</td>
                        <td class="fff-user-column-playground__cell">{!! $row['preview'] ?: '—' !!}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

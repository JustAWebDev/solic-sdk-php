<style type="text/css">
    .solic-form {
        font: 13px Arial, Helvetica, sans-serif;
    }
    .solic-form .form-group {
        margin: 0px 0px 15px 0px;
    }
    .solic-form label {
        display: block;
        margin: 0px 0px 5px 0px;
    }
    .solic-form input{
        width: 100%;

    }
    .solic-form input,
    .solic-form textarea{
        box-sizing: border-box;
        -webkit-box-sizing: border-box;
        -moz-box-sizing: border-box;
        border: 1px solid #C2C2C2;
        box-shadow: 1px 1px 4px #EBEBEB;
        -moz-box-shadow: 1px 1px 4px #EBEBEB;
        -webkit-box-shadow: 1px 1px 4px #EBEBEB;
        border-radius: 3px;
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        padding: 7px;
        outline: none;
    }
    .solic-form input:focus,
    .solic-form textarea:focus{
        border: 1px solid #0C0;
    }
    .solic-form textarea{
        height:100px;
        width: 55%;
    }
    .solic-form input[type=submit],
    .solic-form input[type=button]{
        border: none;
        padding: 8px 15px 8px 15px;
        background: #FF8500;
        color: #fff;
        box-shadow: 1px 1px 4px #DADADA;
        -moz-box-shadow: 1px 1px 4px #DADADA;
        -webkit-box-shadow: 1px 1px 4px #DADADA;
        border-radius: 3px;
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
    }
    .solic-form input[type=submit]:hover,
    .solic-form input[type=button]:hover{
        background: #EA7B00;
        color: #fff;
    }
    @foreach($data->fields as $token)
        @if ($token->type === 'honeypot')
            .form-group.{{ $token->field_token }} {
                display: none;
            }
        @endif
    @endforeach
</style>

<div class="solic-form">
    <form id="{{ $data->name }}" action="{{ $action }}" method="post">
        <input type="hidden" name="csrf_token" value="{{ $data->csrf_token }}" />
        @foreach ($data->fields as $field)
        <div class="form-group {{ $field->field_token }}">
            <label for="{{ $field->field_token }}">{{ $field->name }}</label>
            <input type="{{ $field->type === 'honeypot' ? 'text' : $field->type }}" name="{{ $field->field_token }}" id="{{ $field->field_token }}" />
        </div>
        @endforeach
        <input type="submit" name="submit_button" value="Send" />
    </form>
</div>

@if ($escapeContent === false)
    {!! $content !!}
@else
    {{ $content }}
@endif
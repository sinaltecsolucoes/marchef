$(document).ready(function () {
    // Máscara para telefone (8 ou 9 dígitos)
    var behavior = function (val) {
        return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
    },
        options = {
            onKeyPress: function (val, e, field, options) {
                field.mask(behavior.apply({}, arguments), options);
            }
        };

    $('[data-mask="phone"]').mask(behavior, options);
    $('[data-mask="cpf"]').mask('000.000.000-00');
    $('[data-mask="cep"]').mask('00000-000');
    $('[data-mask="cnpj"]').mask('00.000.000/0000-00');
});
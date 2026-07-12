jQuery(document).ready(function ($) {

    let fieldIndex = $("#asraa-fields-table tbody tr").length;

    /*
    |--------------------------------------------------------------------------
    | Add New Field
    |--------------------------------------------------------------------------
    */

    $("#asraa-add-field").on("click", function (e) {
        e.preventDefault();

        let row = `
            <tr>
                <td>
                    <input type="text" name="asraa_property_fields[${fieldIndex}][label]" placeholder="Field Label">
                </td>

                <td>
                    <input type="text" name="asraa_property_fields[${fieldIndex}][key]" placeholder="field_key">
                </td>

                <td>
                    <select name="asraa_property_fields[${fieldIndex}][type]">
                        <option value="text">Text</option>
                        <option value="number">Number</option>
                        <option value="textarea">Textarea</option>
                        <option value="gallery">Gallery</option>
                        <option value="file">File</option>
                        <option value="url">URL</option>
                        <option value="video">Video</option>
                        <option value="select">Select</option>
                    </select>
                </td>

                <td>
                    <input type="checkbox" name="asraa_property_fields[${fieldIndex}][graphql]" value="1">
                </td>

                <td>
                    <button class="button asraa-remove-field">Remove</button>
                </td>
            </tr>
        `;

        $("#asraa-fields-table tbody").append(row);

        fieldIndex++;
    });

    /*
    |--------------------------------------------------------------------------
    | Remove Field
    |--------------------------------------------------------------------------
    */

    $(document).on("click", ".asraa-remove-field", function (e) {
        e.preventDefault();
        $(this).closest("tr").remove();
    });

});
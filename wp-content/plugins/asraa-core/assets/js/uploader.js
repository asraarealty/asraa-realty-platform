jQuery(document).ready(function ($) {
    let mediaUploader;

    /*
    |--------------------------------------------------------------------------
    | Single File Upload
    |--------------------------------------------------------------------------
    */

    $(".asraa-upload-btn").on("click", function (e) {
        e.preventDefault();

        const target = $(this).data("target");
        const preview = $(this).data("preview");

        mediaUploader = wp.media({
            title: "Select File",
            button: {
                text: "Use this file"
            },
            multiple: false
        });

        mediaUploader.on("select", function () {
            const attachment = mediaUploader
                .state()
                .get("selection")
                .first()
                .toJSON();

            $("#" + target).val(attachment.id);

            if (preview) {
                $("#" + preview).html(
                    '<p><strong>Selected:</strong> ' + attachment.filename + '</p>'
                );
            }
        });

        mediaUploader.open();
    });

    /*
    |--------------------------------------------------------------------------
    | Multiple Gallery Upload
    |--------------------------------------------------------------------------
    */

    $(".asraa-gallery-btn").on("click", function (e) {
        e.preventDefault();

        mediaUploader = wp.media({
            title: "Select Gallery Images",
            button: {
                text: "Use Images"
            },
            multiple: true
        });

        mediaUploader.on("select", function () {
            const attachments = mediaUploader.state().get("selection").toJSON();

            let ids = [];
            let previewHTML = "";

            attachments.forEach(function (attachment) {
                ids.push(attachment.id);

                previewHTML += `
                    <img 
                        src="${attachment.url}" 
                        style="width:100px;height:100px;object-fit:cover;margin:5px;border-radius:8px;"
                    />
                `;
            });

            $("#asraa_gallery").val(ids.join(","));
            $("#asraa-gallery-preview").html(previewHTML);
        });

        mediaUploader.open();
    });

    /*
    |--------------------------------------------------------------------------
    | Remove File
    |--------------------------------------------------------------------------
    */

    $(".asraa-remove-btn").on("click", function (e) {
        e.preventDefault();

        const target = $(this).data("target");
        const preview = $(this).data("preview");

        $("#" + target).val("");

        if (preview) {
            $("#" + preview).html("");
        }
    });
});
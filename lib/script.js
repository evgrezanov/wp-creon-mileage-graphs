jQuery(document).ready(function() {
    var table = jQuery("#teams_result").DataTable({
        dom: "Br<if>tlp",
        order: [[ 1, "desc" ]],
        ordering: true,
        buttons: [
            "copy",
            "excel",
            "pdf",
        ],
    });
});
                    
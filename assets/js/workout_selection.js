// workout_selection.js

$(document).on('change', '.workoutlist', function() {
    var id = $(this).val();
    var action = $('#client-workout_plan-view-button').data("action");
    var user_id = $('#client-workout_plan-view-button').data("user_id");

    // Show loading indicator
    $('#client-data-list').html('<div>Loading...</div>');

    $.ajax({
        url: '/workouts/load_plan',
        type: "POST",
        data: { action: action, id: id, user_id: user_id },
        success: function(data) {
            var response = JSON.parse(data);
            if (response.success) {
                $("#client-data-list").html(response.html);
                $(".select-client-action").removeClass("active");
                if (action == "workout_plan") {
                    $("#client-workout_plan-view-button").addClass("active");
                } else {
                    $("#" + id).addClass("active");
                }
            } else {
                toastr.warning("Your coach is working on your plans and will be available shortly.");
                $("#client-data-list").html('<div>No workout plan available.</div>');
            }
        },
        error: function() {
            toastr.error("Failed to load workout plan.");
            $("#client-data-list").html('<div>Error loading workout plan.</div>');
        }
    });
});
$(document).ready(function() {
    var counts = {
        live: 0,
        dead: 0,
        unknown: 0
    };

    function updateCounter(type) {
        $(`.panel-title.${type} .badge`).text(counts[type]);
    }

    function appendToPanel(type, content) {
        $(`.panel-body.${type}`).append(`<div>${content}</div>`);
    }

    function sendRequest() {
        if (currentIndex < ccLines.length && !stopRequested) {
            var [ccn, month, year, cvc] = ccLines[currentIndex].split("|");

            $.ajax({
                url: "./api.php",
                type: "POST",
                data: {
                    ccn,
                    month,
                    year,
                    cvc
                },
                success: function(response) {
                    if (response.length >= 2) {
                        var status = response[0].status;
                        var message = response[0].message;

                        if (status === "Live") {
                            counts.live++;
                            updateCounter("live");
                            appendToPanel("success", `[${message}] ${ccLines[currentIndex]} ~ ${response[1].bank}|${response[1].country}|${response[1].type}|${response[1].brand}`);
                        }
                    } else if (response.status === "Dead") {
                        counts.dead++;
                        updateCounter("dead");
                        appendToPanel("danger", `[${response.message}] ${ccLines[currentIndex]}`);
                    } else if (response.status === "Unknown") {
                        counts.unknown++;
                        updateCounter("unknown");
                        appendToPanel("warning", ccLines[currentIndex]);
                    }

                    currentIndex++;
                    setTimeout(sendRequest, 3000);
                },
                error: function(status, error) {
                    console.error(status, error);
                    currentIndex++;
                    setTimeout(sendRequest, 3000);
                }
            });
        }
    }

    var currentIndex = 0;
    var stopRequested = false;

    $("#ccData").on("input", function() {
        var allLinesValid = $(this).val().trim().split("\n").every(line => /^(\d{16})\|(\d{2})\|(\d{4})\|(\d{3})$/.test(line.trim()));
        $("#submitBtn").prop("disabled", !allLinesValid);
        $("#stopBtn").prop("disabled", $(this).val().trim() === '');
    });

    $("#form").submit(function(event) {
        event.preventDefault();
        ccLines = $("#ccData").val().trim().split("\n");
        $("#stopBtn").prop("disabled", false);
        sendRequest();
    });

    $("#stopBtn").click(function() {
        stopRequested = true;
    });

    $("#submitBtn").click(function() {
        stopRequested = false;
        sendRequest();
    });
});

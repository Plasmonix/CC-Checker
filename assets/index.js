$(document).ready(function() {
    var currentIndex = 0;
    var stopRequested = false;
    var counts = {
        live: 0,
        dead: 0,
        unknown: 0
    };

    var ccLines = [];

    function updateCounter(type) {
        $(`.panel-title.${type} .badge`).text(counts[type]);
    }

    function appendToPanel(type, message, content) {
        $(`.panel-body.${type}`).append(`<div><span class='badge badge-${type}'>${message}</span>${content}</div>`);
    }

    function removeProcessedLines() {
        var lines = $('#ccData').val().split('\n');
        lines.splice(0, 1);
        $('#ccData').val(lines.join('\n'));
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
                            appendToPanel("success", message, `${ccLines[currentIndex]} ~ ${response[1].bank}|${response[1].country}|${response[1].type}|${response[1].brand}`);
                        }
                    } else if (response.status === "Dead") {
                        counts.dead++;
                        updateCounter("dead");
                        appendToPanel("danger", response.message, `${ccLines[currentIndex]}`);
                    } else if (response.status === "Unknown") {
                        counts.unknown++;
                        updateCounter("unknown");
                        appendToPanel("warning", response.status, ccLines[currentIndex]);
                    }
                    
                    removeProcessedLines();
                    currentIndex++;
                    setTimeout(sendRequest, 3000);
                },
                error: function(xhr) {
                    counts.unknown++;
                    updateCounter("unknown");
                    appendToPanel("warning", "Error", `${JSON.parse(xhr.responseText).error} ~ ${ccLines[currentIndex]}`);
                    removeProcessedLines();
                    currentIndex++;
                    setTimeout(sendRequest, 3000);
                }
            });
        }
    }
    
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
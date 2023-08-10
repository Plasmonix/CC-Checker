var stopRequested = false;
$(document).ready(function() {
    var liveCount = parseInt($(".panel-title.live .badge").text());
    var deadCount = parseInt($(".panel-title.dead .badge").text());
    var unknownCount = parseInt($(".panel-title.unknown .badge").text());

    $("#ccData").on("input", function() {
        var ccData = $(this).val().trim();
        var ccLines = ccData.split("\n");
        var allLinesValid = true;

        for (var i = 0; i < ccLines.length; i++) {
            var line = ccLines[i].trim();
            var pattern = /^(\d{16})\|(\d{2})\|(\d{4})\|(\d{3})$/;
            if (!pattern.test(line)) {
                allLinesValid = false;
                break;
            }
        }

        $("#submitBtn").prop("disabled", !allLinesValid);
    });

    $("#form").submit(function(event) {
        event.preventDefault();
        var ccData = $("#ccData").val().trim();
        var ccLines = ccData.split("\n");
        var currentIndex = 0;
        $("#stopBtn").prop("disabled", false);

        function sendRequest() {
            if (currentIndex < ccLines.length && !stopRequested) {
                var line = ccLines[currentIndex];
                var [ccn, month, year, cvc] = line.split("|");

                $.ajax({
                    url: "./api.php",
                    type: "POST",
                    data: {
                        ccn: ccn,
                        month: month,
                        year: year,
                        cvc: cvc
                    },
                    success: function(response) {
                        if (response.length >= 2) {
                            if (response[0].status === "Live") {
                                liveCount++;
                                $(".panel-title.live .badge").text(liveCount);
                                $(".panel-body.success").append(`<div>[${response[0].message}] ${line}  ~ ${response[1].bank} | ${response[1].country} | ${response[1].type} | ${response[1].brand}</div>`);

                            }
                        } else if (response.status === "Dead") {
                            deadCount++;
                            $(".panel-title.dead .badge").text(deadCount);
                            $(".panel-body.danger").append(`<div>[${response.message}] ${line}</div>`);
                        } else if (response.status === "Unknown") {
                            unknownCount++;
                            $(".panel-title.unknown .badge").text(unknownCount);
                            $(".panel-body.warning").append(`<div>${line}</div>`);
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
        sendRequest();
    });

    $("#stopBtn").click(function() {
        stopRequested = true;
    });

    $("#submitBtn").click(function() {
        if (stopRequested) {
            stopRequested = false; 
            sendRequest(); 
        }
    });
});
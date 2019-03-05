'use strict';

var myDiagram;
var actualEditJobID;
var actualWorkzoneName;
var wzOverview;
var jobTimer;
var bottomJsonEditor;
var jsonEditor;
var jobPredecessorState;
var showValidWz;
var showValidJob;
var datepicker = $("#datepicker");


function validateEditor(values) {
	var isValid = true;
	$.each(values, function (index, value) {
		console.log(value);
		switch (typeof value) {
			case "boolean":
				if (value == false) {
					isValid = false
					console.log("fails on bool");
				}
				break;
			case "number":
				if (value == 0) {
					isValid = false
					console.log("fails on number");
				}
				break;
			case "string":
				if (value == "") {
					isValid = false
					console.log("fails on string");
				}
				break;
			default:
				isValid = false
				console.log("fails on " + (typeof value));
		}
	});
	console.log("is valid:" + isValid);
	console.log(values);
	return isValid;
}


function loadPredecessorStates(jobID) {
	// read the precessor table
	postIt("JobsHandler.php", { action: 8, jobID: jobID }, function (response) {
		console.log(response.schema);
		jobPredecessorState.jobPredecessorStates = response.jobPredecessorStateTable;

	});
}

function openEditor(jobID) {
	// read the precessor table
	loadPredecessorStates(jobID);
	postIt("JobsHandler.php", { action: 5, jobID: jobID }, function (response) {
		console.log(response);
		if (response.content.schema) {

		} else {
			return;
		}
		actualEditJobID = jobID;
		if (jsonEditor) {
			jsonEditor.destroy();
		}
		jsonEditor = new JSONEditor(document.getElementById('editJob'), {
			schema: response.content.schema,
			disable_collapse: true,
			disable_edit_json: true,
			disable_properties: true
		});
		jsonEditor.on('change', function () {
			var values = jsonEditor.getValue();
			validateEditor(values);
			$("#submitJsonEditor").prop("disabled", false);
		});
		postIt("JobsHandler.php", { action: 7, jobID: jobID }, function (response) {
			switchElementVisibility(true);
			if (response) {
				if (response.isMileStone) {
					jobTimer.isMileStone = response.isMileStone;
					delete response.isMileStone;
				}
				if (response.duration) {
					jobTimer.duration = response.duration;
					delete response.duration;
				}
				if (response.endDate) {
					jobTimer.endDate = response.endDate;
					delete response.endDate;
				}
				if (response.owner) {
					jobTimer.owner = response.owner;
					delete response.owner;
				}
				if (response.notmine) {
					jobTimer.notMine = response.notmine;
					delete response.notmine;
				}
			}
			jsonEditor.setValue(response);
			$("#submitJsonEditor").prop("disabled", true);
		});
	});
}

function gotoWorkZoneByName(wzName, data = null) {
	postIt("JobsHandler.php", { action: 3, wzName: wzName }, function (response) {
		wzOverview.workZoneTable = response;
	});
	showWorkZoneByName(wzName);
}

function switchElementVisibility(visible) {
	jobTimer.visible = visible;
	jobPredecessorState.visible = visible;
	bottomJsonEditor.visible = visible;
}

function showWorkZoneByName(wzName) {
	postIt("JobsHandler.php", { action: 4, wzName: wzName }, function (response) {
		myDiagram.model = new go.GraphLinksModel(response["nodes"], response["links"]);
		if (jsonEditor) {
			jsonEditor.destroy();
			switchElementVisibility(false);

		}
		if (response["nodes"].length > 0) {
			actualWorkzoneName = wzName;
			wzOverview.workzoneName = wzName;
		} else {
			actualWorkzoneName = "";
			wzOverview.workzoneName = "";
		}
	});
}


function enableCreateButton() {
	var wz = $('#workzoneInput').val();
	if (wz.length < 3) {
		$("#createFlowButton").prop("disabled", true);
		return;
	}
	var job = $('#jobInput').val();
	if (job.length < 3) {
		$("#createFlowButton").prop("disabled", true);
		return;
	}
	postIt("JobsHandler.php", { action: 1, wzName: wz, jobName: job }, function (response) {
		if (response) {
			$("#createFlowButton").prop("disabled", false);
		} else {
			$("#createFlowButton").prop("disabled", true);
		}
	});
}

function ignorePredecessorJob(edgeID, jobName) {
	if (confirm("Are you SURE to ignore the dependency from " + jobName + "?")) {
		postIt("JobsHandler.php", { action: 9, edgeID: edgeID }, function (response) {
			loadPredecessorStates(actualEditJobID);
		});
	}
}
function acceptPredecessor(edgeID, jobName) {
	if (confirm("Are you SURE to accept the latest changes from " + jobName + "?")) {
		postIt("JobsHandler.php", { action: 10, edgeID: edgeID }, function (response) {
			loadPredecessorStates(actualEditJobID);
		});
	}
}


function postIt(url, data, success) {
	$.ajax({
		type: 'post',
		url: url,
		data: data,
		dataType: 'json'
	}).done(function (data) {
		if (data.errorcode !== 0) {
			alert(data.error);
			return;
		}

		success(data.data);
	});
}


$(function () {


	// ------------------------   Vue defines --------------------------------------

	wzOverview = new Vue({
		el: '#wzOverview',
		data: {
			workZoneTable: [],
			workzoneName: ""
		}
	});

	jobTimer = new Vue({
		el: '#jobTimer',
		methods: {
			installDatePicker: function () {
				$("#datepicker").datepicker({
					showWeek: true,
					firstDay: 1
				});
				$("#submitJsonEditor").click(function () {
					var editorValues = jsonEditor.getValue();
					var validated=validateEditor(editorValues);
					// add values, which are not part of the Editor schema
					editorValues.isMileStone = jobTimer.isMileStone ? 1 : 0;
					editorValues.duration = jobTimer.duration;
					editorValues.endDate = jobTimer.endDate;

					var res = {
						"action": 6,
						"input":
						{
							"jobID": actualEditJobID,
							"predecessorState": 0,
							"validated": validated ? 1 : 0,
							"content": editorValues,
							"state": validated ? 1 : 2
						}
					}
					console.log(editorValues);
					postIt("JobsHandler.php", res, function (response) {
						console.log("values saved on server");
						showWorkZoneByName(actualWorkzoneName);
					});

				});
			}
		},
		data: {
			isMileStone: true,
			duration: 20,
			endDate: new Date().toDateString(),
			visible: true,
			owner: "Klaus Mustermann",
			notMine: true
		},
		mounted: function () {
			this.$nextTick(function () {
				// Code that will run only after the
				// entire view has been rendered
				this.installDatePicker();
			})
		},
		updated: function () {
			this.$nextTick(function () {
				// Code that will run only after the
				// entire view has been rendered
				this.installDatePicker();
			})
		}
	});

	jobPredecessorState = new Vue({
		el: '#jobPredecessorState',
		data: {
			jobPredecessorStates: [],
			visible: true
		},
		methods: {
			// a computed getter
			ignoreButtonText: function (state) {
				// `this` points to the vm instance
				console.log("ignoreButtonText state:", state);
				switch (state) {
					case "6":
						return "Use";
						break;

					default:
						return "Ignore";
				}
			},
			showAcceptButton: function (state) {
				// `this` points to the vm instance
				console.log("showAcceptButton state:", state);
				switch (state) {
					case "3":
						return true;
						break;

					default:
						return false;
				}
			}
		}
	});

	bottomJsonEditor = new Vue({
		el: '#bottomeditdiv',
		data: {
			visible: true
		}
	});

	// ------------------------   jQuery defines --------------------------------------


	$("#tabs").tabs();



	// Add keyup event listeners to our input autocomplete elements

	$('#workzoneInput').autocomplete({
		source: function (request, response) {
			postIt("WorkZones.php", { action: 1, query: request.term }, function (answer) {
				response(answer);
			});
		},
		minLength: 2,
		select: function (event, ui) {
		}
	});
	$('#workzoneInput').change(function () {
		enableCreateButton();
		gotoWorkZoneByName($('#workzoneInput').val());
	});

	$('#jobInput').autocomplete({
		source: function (request, response) {
			postIt("JobTemplates.php", { action: 1, query: request.term }, function (answer) {
				response(answer);
			});
		},
		minLength: 2,
		select: function (event, ui) {
		}
	});
	$('#jobInput').change(function () {
		enableCreateButton();
		gotoWorkZoneByName($('#workzoneInput').val());
	});


	$("#showWorkZone").click(function () {
		gotoWorkZoneByName($('#workzoneInput').val());
	});
	$("#createFlowButton").click(function () {
		postIt("JobsHandler.php", { action: 2, wzName: $('#workzoneInput').val(), jobName: $('#jobInput').val() }, function (response) {
			gotoWorkZoneByName(response.workzonename, response);
		});
	});






	// ------------------------   The diagram --------------------------------------

	var $$ = go.GraphObject.make;  // for conciseness in defining templates, avoid $ due to jQuery
	myDiagram = $$(go.Diagram, "myDiagramDiv",  // create a Diagram for the DIV HTML element
		{
			"undoManager.isEnabled": true  // enable undo & redo
		});
	// define a simple Node template
	myDiagram.nodeTemplate =
		$$(go.Node, "Auto",  // the Shape will go around the TextBlock
			$$(go.Shape, "RoundedRectangle", { strokeWidth: 0 },
				// Shape.fill is bound to Node.data.color
				new go.Binding("fill", "color")
			),
			$$(go.TextBlock,
				{ margin: 8 },  // some room around the text
				// TextBlock.text is bound to Node.data.key
				new go.Binding("text", "text"))
		);

	myDiagram.toolManager.clickSelectingTool.standardMouseSelect = function () {
		var diagram = this.diagram;
		if (diagram === null || !diagram.allowSelect) return;
		var e = diagram.lastInput;
		if (!(e.control || e.meta) && !e.shift) {
			var part = diagram.findPartAt(e.documentPoint, false);
			if (part !== null) {
				var firstselected = null;  // is this or any containing Group selected?
				var node = part;
				while (node !== null) {
					if (node.isSelected) {
						firstselected = node;
						break;
					} else {
						node = node.containingGroup;
					}
				}
				if (firstselected !== null) {
					console.log(firstselected.kb.key);
					openEditor(firstselected.kb.key);
					return;
				}
			}
		}
		go.ClickSelectingTool.prototype.standardMouseSelect.call(this);
	};

	var jsondata = {
		"nodes": [
			{ "key": 1, "text": "Alpha\nbla", "color": "lightblue" },
			{ "key": 2, "text": "Beta", "color": "orange" },
			{ "key": 3, "text": "Gamma", "color": "lightgreen" },
			{ "key": 4, "text": "Delta", "color": "pink" }
		],
		"links": [
			{ "from": 1, "to": 2 },
			{ "from": 1, "to": 3 },
			{ "from": 2, "to": 2 },
			{ "from": 3, "to": 4 },
			{ "from": 4, "to": 1 }
		]
	};


	myDiagram.model = new go.GraphLinksModel(jsondata["nodes"], jsondata["links"]);


	// hide the elements at last, because jquery and vue can't initialize elements when they are invisible

	switchElementVisibility(false);

});


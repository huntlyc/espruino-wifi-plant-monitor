from __future__ import print_function
import urllib2
import sys
import json
import os


# --------------- Helpers that build all of the responses ----------------------

def build_speechlet_response(title, output, reprompt_text, should_end_session, card_output):
    if(card_output == None):
        card_output = output

    return {
        'outputSpeech': {
            'type': 'PlainText',
            'text': output
        },
        'card': {
            'type': 'Simple',
            'title': "Chili Monitor - " + title,
            'content': "Chili Monitor - " + card_output
        },
        'reprompt': {
            'outputSpeech': {
                'type': 'PlainText',
                'text': reprompt_text
            }
        },
        'shouldEndSession': should_end_session
    }


def build_response(session_attributes, speechlet_response):
    return {
        'version': '1.1',
        'sessionAttributes': session_attributes,
        'response': speechlet_response
    }

def get_default_reprompt_text():
    """ The default text to shout back at the user """
    reprompt_text = "Check the status of your plants by asking, " \
                    "how are my chili's doing."
    return reprompt_text


# --------------- Functions that control the skill's behavior ------------------

def get_welcome_response():
    """ If we wanted to initialize the session to have some attributes we could
    add those here
    """

    session_attributes = {}
    card_title = "Welcome to the chilli monitor"
    card_output = None
    speech_output = "Welcome to the chilli monitor system . " \
                    "Check the status of your plants by asking, " \
                    "how are my plants doing."
    # If the user either does not reply to the welcome message or says something
    # that is not understood, they will be prompted again with this text.
    reprompt_text = get_default_reprompt_text()
    should_end_session = False
    return build_response(session_attributes, build_speechlet_response(
        card_title, speech_output, reprompt_text, should_end_session,card_output))


def handle_session_end_request():
    card_title = None
    card_output = None
    reprompt_text = None
    speech_output = "Good bye, have an average day! "
    # Setting this to true ends the session and exits the skill.
    should_end_session = True

    return build_response({}, build_speechlet_response(
        card_title, speech_output, reprompt_text, should_end_session,card_output))

def get_plant_json_from_server():
    return json.loads(get_plant_info_from_server(json=True))

def get_plant_info_from_server(json=False):
    url = os.environ['WEB_ENDPOINT'] + '?auth=' + os.environ['WEB_AUTH_TOKEN']

    if(json == True):
        url += "&type=json"


    request = urllib2.Request(url=url,data=None,headers={"User-Agent":"Magic Browser"})
    response = urllib2.urlopen(request)
    responseContents = response.read();
    return responseContents


def get_detailed_plant_info():
    plantInfo = get_plant_json_from_server()
    t = float(plantInfo['t'])
    m = int(plantInfo['m'])
    date = plantInfo['date']


    """ Build up output text """
    output_text = "The soil recorded a moisture level of " + str(m) + ".  The temperature was recorded at " + str(t) + " degrees celsius.  Date of recording was: " + str(date)

    return output_text


def get_plantInformation(intent, session):
    session_attributes = {}
    reprompt_text = None
    card_title = "Plant Status"
    card_output = get_detailed_plant_info()
    speech_output = get_plant_info_from_server()
    should_end_session = True


    # Setting reprompt_text to None signifies that we do not want to reprompt
    # the user. If the user does not respond or says something that is not
    # understood, the session will end.
    return build_response(session_attributes, build_speechlet_response(
        card_title, speech_output, reprompt_text, should_end_session, card_output))


# --------------- Events ------------------

def on_session_started(session_started_request, session):
    """ Called when the session starts """
    pass

def on_launch(launch_request, session):
    """ Called when the user launches the skill without specifying what they
    want
    """
    return get_welcome_response()


def on_intent(intent_request, session):
    """ Called when the user specifies an intent for this skill """

    intent = intent_request['intent']
    intent_name = intent_request['intent']['name']

    # Dispatch to your skill's intent handlers
    if intent_name == "AllPlantsIntent":
        return get_plantInformation(intent, session)
    elif intent_name == "AMAZON.HelpIntent":
        return get_welcome_response()
    elif intent_name == "AMAZON.CancelIntent" or intent_name == "AMAZON.StopIntent":
        return handle_session_end_request()
    else:
        raise ValueError("Invalid intent")


def on_session_ended(session_ended_request, session):
    """ Called when the user ends the session.

    Is not called when the skill returns should_end_session=true
    """
    pass



# --------------- Main handler ------------------

def lambda_handler(event, context):
    """ Route the incoming request based on type (LaunchRequest, IntentRequest,
    etc.) The JSON body of the request is provided in the event parameter.
    """
    print("event.session.application.applicationId=" +
          event['session']['application']['applicationId'])

    """
    Uncomment this if statement and populate with your skill's application ID to
    prevent someone else from configuring a skill that sends requests to this
    function.
    """
    if (event['session']['application']['applicationId'] !=
             os.environ['ALEXA_APP_ID']):
         raise ValueError("Invalid Application ID")

    if event['session']['new']:
        on_session_started({'requestId': event['request']['requestId']},
                           event['session'])

    if event['request']['type'] == "LaunchRequest":
        return on_launch(event['request'], event['session'])
    elif event['request']['type'] == "IntentRequest":
        return on_intent(event['request'], event['session'])
    elif event['request']['type'] == "SessionEndedRequest":
        return on_session_ended(event['request'], event['session'])

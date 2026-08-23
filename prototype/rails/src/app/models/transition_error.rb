# Raised when a status is asked to move somewhere its transition table does
# not allow.
class TransitionError < StandardError
end

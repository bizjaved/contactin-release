<?php
/**
 * Generic Business Patterns
 *
 * @package ContactInbox\Core\Patterns
 */

declare(strict_types=1);

namespace ContactInbox\Core\Patterns;

if (!defined('ABSPATH')) {
    exit;
}

final class GenericPatterns implements BusinessPatternInterface
{
    public static function get_patterns(): array
    {
    return [
        'sales' => [
            'high' => [
                'price', 'pricing', 'quote', 'purchase', 'buy', 'cost', 'payment', 'invoice', 'demo', 'trial',
                'subscription', 'license', 'plan', 'package deal', 'annual pricing', 'monthly pricing',
                'order', 'ordering', 'place order', 'placed order', 'interested', 'interested in',
                'features', 'specifications', 'specs', 'capabilities', 'requirements', 'licensing',
                'enterprise plan', 'startup plan', 'pro version', 'premium plan', 'basic plan',
                'roi', 'return on investment', 'budget', 'budget for', 'allocate funds', 'investment',
                'how much does', 'how much is', 'what does it cost', 'pricing tier', 'pricing option',
                'competitor', 'alternative', 'switch from', 'migration', 'compare', 'comparison',
                'vs ', 'better than', 'difference between', 'why choose',
            ],
            'medium' => [
                'information about', 'details about', 'offer', 'discount', 'deal', 'package',
                'upgrade', 'request', 'quotation', 'proposal', 'bid', 'estimate', 'free trial', 'sample',
                'demo account', 'test drive', 'evaluate', 'assessment', 'feasibility', 'suitable for',
                'match my needs', 'right solution', 'looking for', 'in the market', 'considering',
                'decide between', 'help me choose', 'business case', 'implementation',
                'shopping for', 'need', 'need a', 'want', 'want a', 'want to buy', 'ready to buy',
                'product', 'item', 'accessories', 'parts', 'model'
            ],
            'low' => [
                'want to know', 'tell me more', 'availability', 'options', 'how much', 'curious',
                'potential', 'possibility', 'might be interested', 'learning about', 'exploring'
            ],
        ],
        'support' => [
            'high' => [
                'help', 'problem', 'issue', 'error', 'bug', 'broken', 'not working', "doesn't work", 'failed',
                'failure', 'failing', 'fails', 'unable to', 'cannot', "can't", 'crash', 'freeze', 
                'unresponsive', 'hang', 'timeout',
                'urgent', 'critical', 'asap', 'sos', 'emergency', 'downtime', 'production down', 'live issue',
                'repair', 'replace', 'fix', 'troubleshoot', 'debug', 'restore', 'recover', 'restart',
                'reinstall', 'reboot', 'reset', 'rebuild', 'rollback',
                'delay', 'not received', 'missing', 'damaged', 'defective', 'faulty', 'broken on arrival',
                'arrived damaged', 'shipment issue', 'delivery problem',
                'support', 'assistance', 'service', 'need help', 'need assistance', 'need support'
            ],
            'medium' => [
                'trouble', 'difficulty', 'stuck', 'slow', 'slow performance', 'lag', 'sluggish',
                'technical', 'malfunction', 'system', 'maintain', 'maintenance', 'patch', 'update issue',
                'resolve', 'solution', 'fix it', 'work again', 'correct', 'rectify',
                'after sales', 'after-sales', 'warranty', 'guarantee', 'coverage', 'protection plan',
                'return', 'exchange', 'replacement', 'refund', 'rebate',
                'error code', 'error message', 'logs', 'diagnostic', 'diagnostic report', 'screenshot',
                'installation', 'setup', 'configure', 'configuration', 'integration', 'migrate', 'migration'
            ],
            'low' => [
                'confused', 'unclear', 'how do i', 'how to', 'having issues', 'install', 'setup', 'configure',
                'guide', 'tutorial', 'help me understand', 'explain', 'learn how', 'best practices'
            ],
        ],
        'feedback' => [
            'high' => [
                'suggest', 'suggestion', 'improvement', 'feature request', 'feature idea', 'new feature',
                'should add', 'should include', 'would be nice', 'could add', 'enhancement', 'enhancement request',
                'proposal', 'recommendation', 'recommend adding', 'request for', 'request you add',
                'user experience', 'ux', 'ui', 'usability', 'workflow', 'process improvement',
                'streamline', 'simplify', 'make easier', 'intuitive', 'user-friendly',
                'missing', 'lacking', 'doesn\'t have', 'no option for', 'can\'t do', 'can\'t handle'
            ],
            'medium' => [
                'feedback', 'idea', 'better if', 'wish', 'wish you had', 'wish it would',
                'consider adding', 'consider including', 'nice to have', 'nice feature',
                'integration', 'api', 'connector', 'plugin', 'extension', 'addon',
                'roadmap', 'future', 'coming soon', 'planned feature', 'backlog',
                'improve', 'enhanced', 'optimize', 'better', 'improvement opportunity'
            ],
            'low' => [
                'thought', 'opinion', 'input', 'perspective', 'consider', 'maybe',
                'just a suggestion', 'food for thought', 'what if', 'imagine'
            ],
        ],
        'complaint' => [
            'high' => [
                'complaint', 'complain', 'unhappy', 'disappointed', 'frustrated',
                'terrible', 'awful', 'horrible', 'worst', 'disgusted', 'angry', 'furious', 'enraged',
                'appalled', 'outraged', 'shameful', 'negligent', 'reckless',
                'refund', 'money back', 'reimburse', 'reimbursement', 'charge back', 'cancel subscription',
                'cancel account', 'unsubscribe', 'stop charges', 'billing issue',
                'poor quality', 'bad quality', 'substandard', 'unacceptable', 'below standard',
                'poor service', 'bad service', 'terrible service', 'rude staff', 'unhelpful',
                'lack of response', 'ignored', 'no support', 'abandoned',
                'shipment damaged', 'not acceptable', 'unacceptable quality',
                'false claims', 'fraud', 'compensation', 'violation', 'overpromise', 'underdeliver'
            ],
            'medium' => [
                'poor', 'unsatisfied', 'not satisfied', 'not happy', 'let down', 'let you down',
                'regret', 'waste of money', 'waste my time', 'wasted', 'ripoff', 'scam',
                'late delivery', 'late shipping', 'slow shipping', 'shipping delay',
                'long wait', 'months late', 'never arrived', 'still waiting',
                'not what i ordered', 'not what i asked for', 'not what i expected',
                'broken', 'not working properly', 'not as described', 'misleading', 'false advertising',
                'breach', 'breach of contract', 'damages', 'litigation',
                'escalate', 'escalation', 'lawyer', 'legal action', 'sue', 'lawsuit'
            ],
            'low' => [
                'expected more', 'not what i expected', 'not what i wanted', 'disappointed', 'unhappy',
                'issues with', 'problems with', 'having trouble with', 'not impressed', 'could be better'
            ],
        ],
        'question' => [
            'high' => [
                'how', 'what', 'when', 'where', 'why', 'which', 'who', 'whom',
                'can you', 'could you', 'would you', 'will you', 'should you', 'do you',
                'is it', 'does it', 'have you', 'has it', 'are you', 'am i',
                'how do i', 'how can i', 'how to', 'how about', 'what is', 'what\'s the best',
                'where can i', 'where is', 'when should i', 'why should i', 'is it possible',
                'is there a way'
            ],
            'medium' => [
                'question', 'questions', 'wondering', 'curious', 'curious about', 'want to know',
                'need to know', 'clarify', 'clarification', 'explain', 'explanation',
                'understand', 'understand how', 'confused about', 'need clarification',
                'help me understand', 'help me know', 'tell me about', 'teach me', 'guidance'
            ],
            'low' => [
                'any chance', 'do you', 'does it', 'is it possible', 'possibility',
                'might be', 'maybe', 'perhaps', 'possibly', 'wondering if'
            ],
        ],
        'spam' => [
            'high' => [
                'click here', 'click now', 'click link', 'buy now', 'limited time', 'act now',
                'free money', 'make money', 'earn money', 'earn $', 'quick cash', 'fast cash',
                'weight loss', 'viagra', 'casino', 'lottery', 'poker', 'slots',
                'congratulations you won', 'you won', 'you are winner', 'chosen you', 'selected you',
                'claim your prize', 'claim prize',
                'verify account', 'confirm account', 'validate account', 'urgent verification','suspicious link',
                'immediate action required', 'act immediately', 'action needed',
                'suspicious activity', 'unauthorized access', 'confirm identity',
                'update payment', 'update credit card', 'update bank info'
            ],
            'medium' => [
                'unsubscribe', 'remove me', 'opt out', 'spam', 'phishing', 'scam', 'suspicious',
                'malware', 'virus', 'trojan', 'ransomware',
                'limited offer', 'final notice', 'last chance', 'expiring soon', 'deadline',
                'hurry', 'don\'t miss out', 'exclusive offer', 'never again',
                'guaranteed', 'guaranteed income', 'risk-free', 'no obligation', 'no catch',
                'hidden fees', 'work from home', 'easy money', 'passive income'
            ],
            'low' => [
                'http://', 'https://', 'www.', 'bit.ly', 'goo.gl', 'tinyurl',
                'follow us', 'like us', 'share us', 'subscribe now', 'join us'
            ],
        ],
    ];
    }
}